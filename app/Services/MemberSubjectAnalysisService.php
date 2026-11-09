<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\TryoutAttempt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Counts every pinned tryout item for each completed attempt, including blanks.
 * Snapshot codes/names take priority over mutable master taxonomy.
 * Missing snapshots (old test fixtures) fall back to the current taxonomy.
 */
final class MemberSubjectAnalysisService
{
    /** @return Collection<int, object> */
    public function forUser(int $userId): Collection
    {
        return $this->summarize($userId);
    }

    /** @return Collection<int, object> */
    public function forAttempt(TryoutAttempt $attempt): Collection
    {
        return $this->summarize((int) $attempt->user_id, (int) $attempt->id, false);
    }

    /** @return Collection<int, object> */
    private function summarize(int $userId, ?int $attemptId = null, bool $includeEmpty = true): Collection
    {
        $keys = DB::table('question_options')
            ->where('is_correct', true)
            ->groupBy('question_version_id')
            ->selectRaw('question_version_id, MIN(label) AS correct_label');

        $subjectCode = 'COALESCE(items.subject_code_snapshot, subjects.code)';
        $subjectName = 'COALESCE(items.subject_name_snapshot, subjects.name)';
        $query = DB::table('tryout_attempts as attempts')
            ->join('tryout_package_items as items', 'items.tryout_package_id', '=', 'attempts.tryout_package_id')
            ->join('question_versions as versions', 'versions.id', '=', 'items.question_version_id')
            ->join('questions as questions', 'questions.id', '=', 'versions.question_id')
            ->join('topics as topics', 'topics.id', '=', 'questions.topic_id')
            ->join('subjects as subjects', 'subjects.id', '=', 'topics.subject_id')
            ->leftJoin('tryout_attempt_answers as answers', function ($join) {
                $join->on('answers.tryout_attempt_id', '=', 'attempts.id')
                    ->on('answers.tryout_package_item_id', '=', 'items.id');
            })
            ->leftJoinSub($keys, 'correct_keys', function ($join) {
                $join->on('correct_keys.question_version_id', '=', 'versions.id');
            })
            ->where('attempts.user_id', $userId)
            ->whereIn('attempts.status', ['completed', 'timed_out'])
            ->groupByRaw($subjectCode . ', ' . $subjectName)
            ->selectRaw($subjectCode . ' as subject_code, ' . $subjectName . ' as subject_name')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN answers.answer_label IS NOT NULL THEN 1 ELSE 0 END) as answered_count')
            ->selectRaw('SUM(CASE WHEN answers.answer_label IS NOT NULL AND answers.answer_label = correct_keys.correct_label THEN 1 ELSE 0 END) as correct_count');

        if ($attemptId !== null) $query->where('attempts.id', $attemptId);

        $rows = $query->get()->map(fn ($row) => $this->formatRow(
            (string) $row->subject_code,
            (string) $row->subject_name,
            (int) $row->total_count,
            (int) $row->answered_count,
            (int) $row->correct_count,
        ));

        if (!$includeEmpty) return $rows->values();

        // Show empty current categories as before, without rewriting historical
        // categories that have since been renamed or deleted from the master.
        $found = $rows->pluck('code')->all();
        foreach (Subject::query()->orderBy('sort_order')->orderBy('id')->get(['code', 'name']) as $subject) {
            if (!in_array($subject->code, $found, true)) {
                $rows->push($this->formatRow($subject->code, $subject->name, 0, 0, 0));
            }
        }
        return $rows->values();
    }

    private function formatRow(string $code, string $name, int $total, int $answered, int $correct): object
    {
        return (object) [
            'code'=>$code, 'name'=>$name, 'total'=>$total, 'answered'=>$answered,
            'correct'=>$correct, 'wrong'=>max(0, $answered-$correct),
            'unanswered'=>max(0, $total-$answered),
            'accuracy'=>$answered > 0 ? round(100*$correct/$answered, 1) : null,
        ];
    }
}
