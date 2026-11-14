<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\TryoutAttempt;
use Illuminate\Support\Facades\DB;

/** Descriptive, read-only, account-scoped summaries. The three domains are not one score. */
final class MemberDomainAnalyticsService
{
    public function __construct(
        private readonly MemberLearningProgressService $learningProgress,
        private readonly QuickQuizAnalyticsService $quickQuiz,
    ) {}

    public function forUser(int $userId): array
    {
        $final = TryoutAttempt::query()->where('user_id', $userId)
            ->whereIn('status', ['completed', 'timed_out']);
        $catMetrics = (clone $final)->selectRaw(
            'COUNT(*) as sessions, AVG(score_percent) as average_score, MAX(score_percent) as best_score'
        )->first();
        $catTrend = (clone $final)->select(['id', 'finished_at', 'score_percent'])
            ->orderByDesc('finished_at')->orderByDesc('id')->limit(5)->get()->reverse()->values();
        $trendCount = $catTrend->count();
        $catPoints = $catTrend->map(function ($attempt, $index) use ($trendCount): array {
            $score = max(0.0, min(100.0, (float) $attempt->score_percent));
            return [
                'x' => round($trendCount === 1 ? 220 : 40 + 360 * $index / ($trendCount - 1), 2),
                'y' => round(135 - 1.15 * $score, 2),
                'score' => $score,
            ];
        })->all();
        $cat = [
            'sessions' => (int) ($catMetrics->sessions ?? 0),
            'average_score' => $catMetrics->average_score === null ? null : (float) $catMetrics->average_score,
            'best_score' => $catMetrics->best_score === null ? null : (float) $catMetrics->best_score,
            'trend' => $catTrend,
            'points' => $catPoints,
            'polyline' => implode(' ', array_map(fn (array $point) => $point['x'].','.$point['y'], $catPoints)),
        ];

        // Never fetch revoked class titles or progress: active enrollment is checked on every read.
        $enrollments = ClassEnrollment::query()->with('learningClass:id,title,status')
            ->where('user_id', $userId)->where('status', 'active')
            ->whereHas('learningClass')
            ->orderByDesc('approved_at')->orderByDesc('id')->limit(3)->get();
        $classCount = ClassEnrollment::query()->where('user_id', $userId)
            ->where('status', 'active')->whereHas('learningClass')->count();
        $ids = $enrollments->pluck('class_id')->map(fn ($id) => (int) $id)->all();
        $progress = $this->learningProgress->summaries($ids, $userId);
        $rows = $enrollments->map(function (ClassEnrollment $enrollment) use ($progress): array {
            $summary = $progress[(int) $enrollment->class_id] ?? ['total' => 0, 'completed' => 0, 'percent' => 0];
            return [
                'class_id' => (int) $enrollment->class_id,
                'title' => $enrollment->learningClass->title,
                'total' => (int) $summary['total'],
                'completed' => (int) $summary['completed'],
                'percent' => (int) $summary['percent'],
            ];
        })->all();
        // Final class practice is independent of progress; include only currently enrolled classes.
        $classPracticeSessions = (int) DB::table('class_practice_attempts as attempt')
            ->join('class_practices as practice', 'practice.id', '=', 'attempt.class_practice_id')
            ->join('enrollments as enrollment', function ($join) use ($userId): void {
                $join->on('enrollment.class_id', '=', 'practice.class_id')
                    ->where('enrollment.user_id', '=', $userId)
                    ->where('enrollment.status', '=', 'active');
            })
            ->where('attempt.user_id', $userId)
            ->whereIn('attempt.status', ['completed', 'timed_out'])->count();
        $classes = [
            'count' => $classCount,
            'practice_sessions' => $classPracticeSessions,
            'previews' => $rows,
            'more' => max(0, $classCount - count($rows)),
        ];

        // Reuse the OWNER-ACCEPTED QQ formulas, including timeout in the locked denominator.
        $qq = $this->quickQuiz->forUser($userId)['summary'];
        $locked = (int) $qq['locked'];
        $correctWidth = $locked > 0 ? round(400 * $qq['correct'] / $locked, 2) : 0.0;
        $wrongWidth = $locked > 0 ? round(400 * $qq['wrong'] / $locked, 2) : 0.0;
        $timeoutWidth = $locked > 0 ? round(400 * $qq['timeout'] / $locked, 2) : 0.0;
        $qq['bar'] = [
            'correct' => $correctWidth,
            'wrong_x' => $correctWidth,
            'wrong' => $wrongWidth,
            'timeout_x' => $correctWidth + $wrongWidth,
            'timeout' => $timeoutWidth,
        ];
        return compact('cat', 'classes', 'qq');
    }
}
