<?php
namespace App\Services;

use App\Models\QuestionOption;
use App\Models\TryoutAttempt;
use App\Models\TryoutAttemptAnswer;
use App\Models\TryoutPackage;
use App\Models\TryoutPackageItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The server alone decides eligibility, deadlines, correct answers and score. */
class MemberTryoutService
{
    public function start(User $user, TryoutPackage $package): TryoutAttempt
    {
        return DB::transaction(function () use ($user, $package) {
            // Serialise simultaneous tabs/devices starting the same user's attempts.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $package = TryoutPackage::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            if ($package->status !== 'published') {
                throw ValidationException::withMessages(['tryout' => 'Tryout belum tersedia untuk peserta.']);
            }
            $active = TryoutAttempt::query()->where('user_id',$user->id)
                ->where('tryout_package_id',$package->id)->where('status','in_progress')
                ->lockForUpdate()->latest('id')->first();
            if ($active) {
                if (now()->gte($active->expires_at)) $this->finishLocked($active);
                else return $active;
            }
            $completedCount = TryoutAttempt::query()->where('user_id',$user->id)->where('tryout_package_id',$package->id)->count();
            if ($completedCount >= $package->max_attempts) {
                throw ValidationException::withMessages(['tryout' => 'Batas percobaan tryout telah tercapai.']);
            }
            $total = $package->items()->count();
            if ($total < 1) {
                throw ValidationException::withMessages(['tryout' => 'Template tryout belum memiliki soal.']);
            }
            $at = now();
            return TryoutAttempt::create([
                'user_id'=>$user->id,'tryout_package_id'=>$package->id,
                'attempt_number'=>$completedCount+1,'status'=>'in_progress',
                'started_at'=>$at,'expires_at'=>$at->copy()->addMinutes($package->duration_minutes),
                'total_questions'=>$total,
            ]);
        }, 3);
    }

    /** Finish only when the deadline passed; this is idempotent under row locking. */
    public function expire(TryoutAttempt $attempt): TryoutAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $locked=TryoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'in_progress' && now()->gte($locked->expires_at)) $this->finishLocked($locked);
            return $locked->refresh();
        }, 3);
    }

    public function save(TryoutAttempt $attempt, int $itemId, ?string $answer, bool $flagged): array
    {
        return DB::transaction(function () use ($attempt,$itemId,$answer,$flagged) {
            $locked=TryoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'in_progress' && now()->gte($locked->expires_at)) $this->finishLocked($locked);
            if ($locked->status !== 'in_progress') return ['closed'=>true];
            $item=TryoutPackageItem::query()->whereKey($itemId)
                ->where('tryout_package_id',$locked->tryout_package_id)->first();
            if (!$item) throw ValidationException::withMessages(['item_id'=>'Nomor soal tidak terdaftar dalam sesi ini.']);
            if ($answer !== null && !QuestionOption::query()->where('question_version_id',$item->question_version_id)->where('label',$answer)->exists()) {
                throw ValidationException::withMessages(['answer'=>'Pilihan jawaban tidak tersedia untuk soal ini.']);
            }
            TryoutAttemptAnswer::query()->updateOrCreate(
                ['tryout_attempt_id'=>$locked->id,'tryout_package_item_id'=>$itemId],
                ['answer_label'=>$answer,'flagged'=>$flagged]
            );
            return ['closed'=>false,'answered'=>TryoutAttemptAnswer::query()->where('tryout_attempt_id',$locked->id)->whereNotNull('answer_label')->count()];
        }, 3);
    }

    public function submit(TryoutAttempt $attempt): TryoutAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $locked=TryoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status==='in_progress') $this->finishLocked($locked);
            return $locked->refresh();
        }, 3);
    }

    /** MUST be called while holding the attempt's SELECT FOR UPDATE lock. */
    private function finishLocked(TryoutAttempt $attempt): void
    {
        $items=TryoutPackageItem::query()->where('tryout_package_id',$attempt->tryout_package_id)
            ->with(['version.options'])->get();
        $answers=TryoutAttemptAnswer::query()->where('tryout_attempt_id',$attempt->id)->get()->keyBy('tryout_package_item_id');
        $correct=0; $answered=0;
        foreach ($items as $item) {
            $choice=$answers->get($item->id)?->answer_label;
            if ($choice === null) continue;
            $answered++;
            $key=$item->version?->options?->firstWhere('is_correct',true)?->label;
            if ($key !== null && $choice === $key) $correct++;
        }
        $total=count($items);
        $attempt->forceFill([
            'status'=>now()->gte($attempt->expires_at)?'timed_out':'completed',
            'finished_at'=>now(),
            'total_questions'=>$total,
            'answered_count'=>$answered,
            'correct_count'=>$correct,
            'score_percent'=>$total?round($correct*100/$total,2):0,
        ])->save();
    }
}
