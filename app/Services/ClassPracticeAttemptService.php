<?php
namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\ClassPractice;
use App\Models\ClassPracticeAnswer;
use App\Models\ClassPracticeAttempt;
use App\Models\ClassPracticeItem;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Class-specific attempt engine. Does not write to CAT history or material progress. */
class ClassPracticeAttemptService
{
    public function assertEnrolled(int $classId, int $userId, bool $lock=false): void
    {
        $query=ClassEnrollment::query()->where('class_id',$classId)->where('user_id',$userId);
        $record=$lock ? $query->lockForUpdate()->first() : $query->first();
        abort_unless($record?->status==='active',404);
    }

    public function start(User $user, ClassPractice $practice): ClassPracticeAttempt
    {
        return DB::transaction(function() use($user,$practice): ClassPracticeAttempt {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertEnrolled((int)$practice->class_id,(int)$user->id,true);
            $locked=ClassPractice::query()->whereKey($practice->id)->lockForUpdate()->firstOrFail();
            $active=ClassPracticeAttempt::query()->where('user_id',$user->id)
                ->where('class_practice_id',$locked->id)->where('status','in_progress')
                ->lockForUpdate()->latest('id')->first();
            if ($active) {
                if (now()->gte($active->expires_at)) $this->finishLocked($active);
                else return $active;
            }
            if ($locked->status!=='published') {
                throw ValidationException::withMessages(['practice'=>'Latihan tidak tersedia untuk percobaan baru.']);
            }
            $count=ClassPracticeAttempt::query()->where('user_id',$user->id)->where('class_practice_id',$locked->id)->count();
            if ($count >= $locked->max_attempts) throw ValidationException::withMessages(['practice'=>'Batas percobaan latihan tercapai.']);
            $total=$locked->items()->count();
            if (!$total) throw ValidationException::withMessages(['practice'=>'Latihan belum memiliki soal.']);
            $now=now();
            return ClassPracticeAttempt::query()->create([
                'class_practice_id'=>$locked->id,'user_id'=>$user->id,'attempt_number'=>$count+1,
                'status'=>'in_progress','started_at'=>$now,'expires_at'=>$now->copy()->addMinutes($locked->duration_minutes),
                'total_questions'=>$total,
            ]);
        },3);
    }

    public function expire(ClassPracticeAttempt $attempt): ClassPracticeAttempt
    {
        return DB::transaction(function() use($attempt): ClassPracticeAttempt {
            $this->assertEnrolled((int)$attempt->practice->class_id,(int)$attempt->user_id,true);
            $locked=ClassPracticeAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status==='in_progress' && now()->gte($locked->expires_at)) $this->finishLocked($locked);
            return $locked->refresh();
        },3);
    }

    public function save(ClassPracticeAttempt $attempt, int $itemId, ?string $answer, bool $flagged): array
    {
        return DB::transaction(function() use($attempt,$itemId,$answer,$flagged): array {
            $this->assertEnrolled((int)$attempt->practice->class_id,(int)$attempt->user_id,true);
            $locked=ClassPracticeAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status==='in_progress' && now()->gte($locked->expires_at)) $this->finishLocked($locked);
            if ($locked->status!=='in_progress') return ['closed'=>true];
            $item=ClassPracticeItem::query()->whereKey($itemId)->where('class_practice_id',$locked->class_practice_id)->first();
            if (!$item) throw ValidationException::withMessages(['item_id'=>'Soal tidak termasuk dalam latihan ini.']);
            if ($answer!==null && !QuestionOption::query()->where('question_version_id',$item->question_version_id)
                ->where('label',$answer)->exists()) throw ValidationException::withMessages(['answer'=>'Pilihan jawaban tidak valid.']);
            ClassPracticeAnswer::query()->updateOrCreate(
                ['class_practice_attempt_id'=>$locked->id,'class_practice_item_id'=>$item->id],
                ['answer_label'=>$answer,'flagged'=>$flagged]
            );
            return ['closed'=>false,'answered'=>ClassPracticeAnswer::query()
                ->where('class_practice_attempt_id',$locked->id)->whereNotNull('answer_label')->count()];
        },3);
    }

    public function submit(ClassPracticeAttempt $attempt): ClassPracticeAttempt
    {
        return DB::transaction(function() use($attempt): ClassPracticeAttempt {
            $this->assertEnrolled((int)$attempt->practice->class_id,(int)$attempt->user_id,true);
            $locked=ClassPracticeAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status==='in_progress') $this->finishLocked($locked);
            return $locked->refresh();
        },3);
    }

    /** Caller must hold the attempt lock. Score is based only on published pinned versions. */
    private function finishLocked(ClassPracticeAttempt $attempt): void
    {
        $items=ClassPracticeItem::query()->where('class_practice_id',$attempt->class_practice_id)
            ->with('version.options')->get();
        $answers=ClassPracticeAnswer::query()->where('class_practice_attempt_id',$attempt->id)
            ->get()->keyBy('class_practice_item_id');
        $correct=0;
        $answered=0;
        foreach ($items as $item) {
            $choice=$answers->get($item->id)?->answer_label;
            if ($choice===null) continue;
            $answered++;
            $key=$item->version?->options?->firstWhere('is_correct',true)?->label;
            if ($key!==null && $key===$choice) $correct++;
        }
        $total=$items->count();
        $attempt->forceFill([
            'status'=>now()->gte($attempt->expires_at)?'timed_out':'completed',
            'finished_at'=>now(),'total_questions'=>$total,'answered_count'=>$answered,
            'correct_count'=>$correct,'score_percent'=>$total?round($correct*100/$total,2):0,
        ])->save();
    }
}
