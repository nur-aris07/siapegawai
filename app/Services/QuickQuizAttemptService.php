<?php

namespace App\Services;

use App\Models\QuickQuiz;
use App\Models\QuickQuizAttempt;
use App\Models\QuickQuizAttemptAnswer;
use App\Models\QuickQuizQuotaUsage;
use App\Models\QuickQuizVersionItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** QQ attempts have independent lifecycle and history; never write CAT / Class Practice tables. */
class QuickQuizAttemptService
{
    private const RECOVERY_MINUTES = 30;
    public const FEEDBACK_SECONDS = 5;

    public function __construct(private readonly QuickQuizQuotaService $quota) {}

    /** One active attempt per participant across quizzes, preventing double-tab starts. */
    public function start(User $user, QuickQuiz $quiz, string $key): QuickQuizAttempt
    {
        if (!preg_match('/^[A-Za-z0-9_-]{12,80}$/D', $key)) {
            throw ValidationException::withMessages(['attempt'=>'Permintaan memulai tidak valid.']);
        }
        return DB::transaction(function () use ($user,$quiz,$key) {
            // Same user-row mutex as the quota service, held across active-check and debit.
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($lockedUser->role==='participant' && $lockedUser->status==='active', 403);
            $already = QuickQuizAttempt::query()->where('user_id',$user->id)
                ->where('status','active')->orderBy('id')->lockForUpdate()->get();
            foreach ($already as $attempt) {
                $this->settle($attempt, CarbonImmutable::now('UTC'));
                if ($attempt->status === 'active') return $attempt;
            }
            // Old idempotency keys always return their old attempt, not a new charge.
            $existing = QuickQuizAttempt::query()->where('user_id',$user->id)->where('start_key',$key)->first();
            if ($existing) return $existing;
            $quiz = QuickQuiz::query()->lockForUpdate()->findOrFail($quiz->id);
            abort_unless($quiz->status === 'published' && $quiz->current_version_id, 404);
            $version = $quiz->currentVersion;
            abort_unless($version && $version->status === 'published', 404);
            $count = $version->items()->count();
            if ($count < 30 || $count > 40) {
                throw ValidationException::withMessages(['attempt'=>'Susunan quiz terbit tidak valid.']);
            }
            $now = CarbonImmutable::now('UTC');
            $charged = $this->quota->startWithQuota($lockedUser, $key, function () use ($lockedUser,$version,$count,$key,$now): string {
                $attempt = QuickQuizAttempt::query()->create([
                    'user_id'=>$lockedUser->id,'quick_quiz_version_id'=>$version->id,
                    'start_key'=>$key,'status'=>'active','phase'=>'question','current_index'=>0,
                    'total_questions'=>$count,'started_at'=>$now,'last_seen_at'=>$now,
                    'question_started_at'=>$now,'question_expires_at'=>$now->addSeconds($version->seconds_per_question),
                ]);
                return 'qq:'.$attempt->id;
            }, $now);
            if (!str_starts_with($charged['attempt_ref'], 'qq:')) throw new \LogicException('Referensi attempt QQ tidak sesuai.');
            $attempt = QuickQuizAttempt::query()->findOrFail((int) substr($charged['attempt_ref'],3));
            abort_unless((int)$attempt->user_id === (int)$user->id,403);
            if (!$attempt->qq_quota_usage_id) {
                $attempt->update(['qq_quota_usage_id'=>$charged['usage_id']]);
            }
            return $attempt;
        }, 3);
    }

    /** Safe read transitions; no timer for an unissued next question. */
    public function read(QuickQuizAttempt $attempt): QuickQuizAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt = QuickQuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->settle($attempt, CarbonImmutable::now('UTC'));
            return $attempt->fresh();
        }, 3);
    }

    /** Idempotent answer. The server decides whether the request reached its deadline. */
    public function answer(QuickQuizAttempt $attempt, int $itemId, ?string $label): QuickQuizAttempt
    {
        return DB::transaction(function () use ($attempt,$itemId,$label) {
            $attempt = QuickQuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $now=CarbonImmutable::now('UTC');
            $this->settle($attempt,$now);
            abort_unless($attempt->status==='active',409);
            $item=$this->currentItem($attempt);
            abort_unless($item && (int)$item->id===$itemId,409);
            if ($attempt->phase==='feedback') return $attempt->fresh(); // replay: never write twice
            abort_unless($attempt->phase==='question',409);
            if ($label === null || !$item->version->options->contains('label',$label)) {
                throw ValidationException::withMessages(['answer'=>'Pilihan tidak valid.']);
            }
            $this->lockAnswer($attempt,$item,$label,$now);
            return $attempt->fresh();
        },3);
    }

    /** Explicit feedback acknowledgement: only this action issues the next question. */
    public function next(QuickQuizAttempt $attempt): QuickQuizAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt = QuickQuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $now=CarbonImmutable::now('UTC');
            $this->settle($attempt,$now);
            abort_unless($attempt->status==='active' && $attempt->phase==='feedback',409);
            if ($attempt->feedback_until->greaterThan($now)) {
                throw ValidationException::withMessages(['feedback'=>'Feedback belum selesai ditampilkan.']);
            }
            if ($attempt->current_index + 1 >= $attempt->total_questions) {
                $this->finish($attempt,$now);
            } else {
                $attempt->update([
                    'phase'=>'question','current_index'=>$attempt->current_index+1,
                    'question_started_at'=>$now,
                    'question_expires_at'=>$now->addSeconds($attempt->version->seconds_per_question),
                    'feedback_until'=>null,'last_seen_at'=>$now,
                ]);
            }
            return $attempt->fresh();
        },3);
    }

    private function settle(QuickQuizAttempt $attempt, CarbonImmutable $now): void
    {
        if ($attempt->status !== 'active') return;
        // Recovery is independent of login-session lifetime; do not consume unseen questions.
        if ($attempt->last_seen_at->lt($now->subMinutes(self::RECOVERY_MINUTES))) {
            if ($attempt->phase==='question' && $attempt->question_expires_at->lte($now)) {
                $item=$this->currentItem($attempt);
                if ($item) $this->lockAnswer($attempt,$item,null,$now);
            }
            $this->finish($attempt,$now,'abandoned');
            return;
        }
        if ($attempt->phase==='question' && $attempt->question_expires_at->lte($now)) {
            $item=$this->currentItem($attempt);
            if ($item) $this->lockAnswer($attempt,$item,null,$now);
        }
        $attempt->update(['last_seen_at'=>$now]);
    }

    private function lockAnswer(QuickQuizAttempt $attempt, QuickQuizVersionItem $item, ?string $label, CarbonImmutable $now): void
    {
        $correct = $item->version->options->firstWhere('is_correct',true);
        if (!$correct) throw new \LogicException('Published question has no correct option.');
        $elapsed=max(0,(int)$attempt->question_started_at->diffInMilliseconds($now));
        $allowed=(int)$attempt->version->seconds_per_question*1000;
        $timeout=$label===null || $attempt->question_expires_at->lte($now);
        QuickQuizAttemptAnswer::query()->firstOrCreate(
            ['quick_quiz_attempt_id'=>$attempt->id,'quick_quiz_version_item_id'=>$item->id],
            ['answer_label'=>$timeout?null:$label,'status'=>$timeout?'timeout':'answered',
                'is_correct'=>!$timeout && $label===$correct->label,'response_ms'=>min($allowed,$elapsed),
                'locked_at'=>$now]);
        $attempt->update(['phase'=>'feedback','feedback_until'=>$now->addSeconds(self::FEEDBACK_SECONDS),'last_seen_at'=>$now]);
    }

    private function finish(QuickQuizAttempt $attempt, CarbonImmutable $now, string $status = 'completed'): void
    {
        $correct = (int)$attempt->answers()->where('is_correct',true)->count();
        $attempt->update(['status'=>$status,'phase'=>'finished','finished_at'=>$now,'last_seen_at'=>$now,
            'correct_count'=>$correct,'score_percent'=>round(100*$correct/max(1,$attempt->total_questions),2)]);
    }

    public function currentItem(QuickQuizAttempt $attempt): ?QuickQuizVersionItem
    {
        return QuickQuizVersionItem::query()->where('quick_quiz_version_id',$attempt->quick_quiz_version_id)
            ->where('sort_order',$attempt->current_index+1)->with('version.options')->first();
    }
}
