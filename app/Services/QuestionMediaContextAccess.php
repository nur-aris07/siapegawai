<?php
namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\ClassPracticeAttempt;
use App\Models\ClassPracticeItem;
use App\Models\QuickQuizAttempt;
use App\Models\QuickQuizAttemptAnswer;
use App\Models\QuickQuizVersionItem;
use App\Models\QuestionVersionMedia;
use App\Models\TryoutAttempt;
use App\Models\TryoutPackageItem;

/** Authorization for private participant image GET; SELECT only, never settle/expire/issue/charge. */
final class QuestionMediaContextAccess
{
    public function authorize(string $domain, int $userId, int $attemptId, int $itemId, QuestionVersionMedia $media, ?int $classId = null): void
    {
        // Never disclose a draft reference even if an item has been assembled in malformed data.
        $mediaVersion = $media->version;
        abort_unless($mediaVersion && $mediaVersion->review_status === 'published'
            && $mediaVersion->published_at !== null, 404);
        abort_unless(in_array($media->slot, [
            'stem','option_A','option_B','option_C','option_D','option_E','explanation',
        ], true), 404);
        if ($domain === 'qq') {
            $this->qq($userId, $attemptId, $itemId, $media);
            return;
        }
        if ($domain === 'cat') {
            $this->cat($userId, $attemptId, $itemId, $media);
            return;
        }
        if ($domain === 'class' && $classId !== null) {
            $this->practice($userId, $attemptId, $itemId, $media, $classId);
            return;
        }
        abort(404);
    }

    private function qq(int $userId, int $attemptId, int $itemId, QuestionVersionMedia $media): void
    {
        $attempt = QuickQuizAttempt::query()->whereKey($attemptId)->where('user_id',$userId)->first();
        abort_unless($attempt, 404);
        $pilot=app(PilotAccessService::class);
        if ($pilot->enabled()) { $u=\App\Models\User::query()->find($userId); abort_unless($u && $pilot->admitted($u,$attempt),404); }
        $item = QuickQuizVersionItem::query()->whereKey($itemId)
            ->where('quick_quiz_version_id',$attempt->quick_quiz_version_id)
            ->where('question_version_id',$media->question_version_id)->first();
        abort_unless($item, 404);
        $locked = QuickQuizAttemptAnswer::query()->where('quick_quiz_attempt_id',$attemptId)
            ->where('quick_quiz_version_item_id',$itemId)->exists();
        if ($attempt->status === 'active') {
            abort_unless((int)$item->sort_order === (int)$attempt->current_index + 1, 404);
            if ($attempt->phase === 'question') {
                // A late image GET must not extend a timed-out question or synthesize settlement.
                abort_unless(!$locked && $attempt->question_expires_at && now('UTC')->lt($attempt->question_expires_at), 404);
                abort_if($media->slot === 'explanation', 404);
                return;
            }
            abort_unless($attempt->phase === 'feedback' && $locked, 404);
            return;
        }
        // Finished/abandoned review contains ONLY persisted locked items (not upcoming pins).
        abort_unless(in_array($attempt->status, ['completed','abandoned'], true) && $locked, 404);
    }

    private function cat(int $userId, int $attemptId, int $itemId, QuestionVersionMedia $media): void
    {
        $attempt = TryoutAttempt::query()->whereKey($attemptId)->where('user_id',$userId)->first();
        abort_unless($attempt, 404);
        abort_unless(TryoutPackageItem::query()->whereKey($itemId)
            ->where('tryout_package_id',$attempt->tryout_package_id)
            ->where('question_version_id',$media->question_version_id)->exists(), 404);
        if ($attempt->status === 'in_progress') {
            abort_unless($attempt->expires_at && now()->lt($attempt->expires_at), 404);
            abort_if($media->slot === 'explanation', 404);
            return;
        }
        abort_unless(in_array($attempt->status, ['completed','timed_out'], true), 404);
    }

    private function practice(int $userId, int $attemptId, int $itemId, QuestionVersionMedia $media, int $classId): void
    {
        $attempt = ClassPracticeAttempt::query()->whereKey($attemptId)->where('user_id',$userId)
            ->whereHas('practice', fn($q) => $q->where('class_id',$classId))->first();
        abort_unless($attempt, 404);
        abort_unless(ClassEnrollment::query()->where('class_id',$classId)->where('user_id',$userId)
            ->where('status','active')->exists(), 404);
        abort_unless(ClassPracticeItem::query()->whereKey($itemId)
            ->where('class_practice_id',$attempt->class_practice_id)
            ->where('question_version_id',$media->question_version_id)->exists(), 404);
        if ($attempt->status === 'in_progress') {
            abort_unless($attempt->expires_at && now()->lt($attempt->expires_at), 404);
            abort_if($media->slot === 'explanation', 404);
            return;
        }
        abort_unless(in_array($attempt->status, ['completed','timed_out'], true), 404);
    }
}
