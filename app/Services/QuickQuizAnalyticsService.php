<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Read-only aggregation of server-locked, completed Quiz Kilat attempts for ONE participant. */
final class QuickQuizAnalyticsService
{
    public function forUser(int $userId): array
    {
        $completed = DB::table('quick_quiz_attempts as a')
            ->where('a.user_id', $userId)->where('a.status', 'completed');

        $attemptMetrics = (clone $completed)->selectRaw(
            'COUNT(*) as sessions, AVG(a.score_percent) as average_score, MAX(a.score_percent) as best_score'
        )->first();

        // Do not count issued-but-unlocked questions or abandoned sessions as final results.
        // Timeout time is intentionally excluded from average *answered* response time.
        $locked = DB::table('quick_quiz_attempt_answers as ans')
            ->join('quick_quiz_attempts as a', 'a.id', '=', 'ans.quick_quiz_attempt_id')
            ->where('a.user_id', $userId)->where('a.status', 'completed');

        $answerMetrics = (clone $locked)->selectRaw(
            "COUNT(*) as locked_total,
             SUM(CASE WHEN ans.status = 'answered' AND ans.is_correct = 1 THEN 1 ELSE 0 END) as correct_total,
             SUM(CASE WHEN ans.status = 'answered' AND ans.is_correct = 0 THEN 1 ELSE 0 END) as wrong_total,
             SUM(CASE WHEN ans.status = 'timeout' THEN 1 ELSE 0 END) as timeout_total,
             AVG(CASE WHEN ans.status = 'answered' THEN ans.response_ms ELSE NULL END) as average_response_ms"
        )->first();

        $sessions = (int) ($attemptMetrics->sessions ?? 0);
        $correct = (int) ($answerMetrics->correct_total ?? 0);
        $total = (int) ($answerMetrics->locked_total ?? 0);
        $summary = [
            'sessions' => $sessions,
            'average_score' => $attemptMetrics->average_score === null ? null : (float) $attemptMetrics->average_score,
            'best_score' => $attemptMetrics->best_score === null ? null : (float) $attemptMetrics->best_score,
            'locked' => $total,
            'correct' => $correct,
            'wrong' => (int) ($answerMetrics->wrong_total ?? 0),
            'timeout' => (int) ($answerMetrics->timeout_total ?? 0),
            'accuracy' => $total > 0 ? round(100 * $correct / $total, 1) : null,
            'average_response_ms' => $answerMetrics->average_response_ms === null ? null : (float) $answerMetrics->average_response_ms,
        ];

        // Group by immutable snapshots stored on the published quiz-version items.
        // No join with mutable subject/topic masters is permitted.
        $snapshots = (clone $locked)
            ->join('quick_quiz_version_items as item', 'item.id', '=', 'ans.quick_quiz_version_item_id');

        $groupCounts = "COUNT(*) as total,
            SUM(CASE WHEN ans.status = 'answered' AND ans.is_correct = 1 THEN 1 ELSE 0 END) as correct,
            SUM(CASE WHEN ans.status = 'answered' AND ans.is_correct = 0 THEN 1 ELSE 0 END) as wrong,
            SUM(CASE WHEN ans.status = 'timeout' THEN 1 ELSE 0 END) as timeout";

        $subjects = (clone $snapshots)
            ->select('item.subject_code_snapshot', 'item.subject_name_snapshot')
            ->selectRaw($groupCounts)
            ->groupBy('item.subject_code_snapshot', 'item.subject_name_snapshot')
            ->orderBy('item.subject_code_snapshot')
            ->orderBy('item.subject_name_snapshot')
            ->get();

        $topics = (clone $snapshots)
            ->select('item.subject_code_snapshot', 'item.subject_name_snapshot', 'item.topic_name_snapshot')
            ->selectRaw($groupCounts)
            ->groupBy('item.subject_code_snapshot', 'item.subject_name_snapshot', 'item.topic_name_snapshot')
            ->orderBy('item.subject_code_snapshot')
            ->orderBy('item.topic_name_snapshot')
            ->get();

        $trend = (clone $completed)
            ->join('quick_quiz_versions as v', 'v.id', '=', 'a.quick_quiz_version_id')
            ->select('a.id', 'a.finished_at', 'a.score_percent', 'a.correct_count', 'a.total_questions',
                'v.title', 'v.version_number')
            ->orderByDesc('a.finished_at')->orderByDesc('a.id')->limit(8)->get()
            ->reverse()->values();

        return compact('summary', 'subjects', 'topics', 'trend');
    }
}
