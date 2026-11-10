<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\ClassPracticeAttempt;
use App\Models\LearningClass;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Read-only projections. Never mutates material progress or practice scores. */
class ClassMonitoringService
{
    private function completedSubquery(int $classId): Builder
    {
        return DB::table('learning_material_progress as progress')
            ->join('learning_materials as materials', 'materials.id', '=', 'progress.material_id')
            ->where('materials.class_id', $classId)
            ->where('materials.status', 'published')
            ->select('progress.user_id')
            ->selectRaw('COUNT(*) AS completed_count')
            ->groupBy('progress.user_id');
    }

    private function practiceSummarySubquery(int $classId): Builder
    {
        return DB::table('class_practice_attempts as attempts')
            ->join('class_practices as practices', 'practices.id', '=', 'attempts.class_practice_id')
            ->where('practices.class_id', $classId)
            ->whereIn('attempts.status', ['completed', 'timed_out'])
            ->select('attempts.user_id')
            ->selectRaw('COUNT(*) AS finished_attempts, MAX(attempts.score_percent) AS best_score')
            ->groupBy('attempts.user_id');
    }

    /** @return array{publishedMaterials:int,publishedPractices:int,active:int,revoked:int,progressed:int,averagePercent:int,finishedAttempts:int} */
    public function totals(LearningClass $class): array
    {
        $id = (int) $class->id;
        $published = DB::table('learning_materials')->where('class_id', $id)->where('status', 'published')->count();
        $practices = DB::table('class_practices')->where('class_id', $id)->where('status', 'published')->count();
        $status = DB::table('enrollments')->where('class_id', $id)
            ->whereIn('status', ['active', 'revoked'])
            ->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $active = (int) ($status['active'] ?? 0);
        $activeProgress = DB::table('enrollments as enrollment')
            ->join('users as user', function ($join): void {
                $join->on('user.id', '=', 'enrollment.user_id')->where('user.role', '=', 'participant');
            })
            ->leftJoinSub($this->completedSubquery($id), 'completion', 'completion.user_id', '=', 'enrollment.user_id')
            ->where('enrollment.class_id', $id)->where('enrollment.status', 'active')
            ->selectRaw('COALESCE(SUM(COALESCE(completion.completed_count, 0)), 0) AS completed_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(completion.completed_count, 0) > 0 THEN 1 ELSE 0 END), 0) AS progressed')
            ->first();
        $finished = DB::table('class_practice_attempts as attempts')
            ->join('class_practices as practices', 'practices.id', '=', 'attempts.class_practice_id')
            ->where('practices.class_id', $id)
            ->whereIn('attempts.status', ['completed', 'timed_out'])->count();
        return [
            'publishedMaterials' => $published,
            'publishedPractices' => $practices,
            'active' => $active,
            'revoked' => (int) ($status['revoked'] ?? 0),
            'progressed' => (int) ($activeProgress?->progressed ?? 0),
            'averagePercent' => ($published === 0 || $active === 0) ? 0
                : (int) round((int) ($activeProgress?->completed_total ?? 0) * 100 / ($published * $active)),
            'finishedAttempts' => $finished,
        ];
    }

    /** Unfiltered query for the shared HTML/Yajra monitoring list (scoped to one class). */
    public function enrollmentTableQuery(LearningClass $class): Builder
    {
        $id = (int) $class->id;
        $query = DB::table('enrollments as enrollment')
            ->join('users as user', function ($join): void {
                $join->on('user.id', '=', 'enrollment.user_id')->where('user.role', '=', 'participant');
            })
            ->leftJoinSub($this->completedSubquery($id), 'completion', 'completion.user_id', '=', 'enrollment.user_id')
            ->leftJoinSub($this->practiceSummarySubquery($id), 'practice_stats', 'practice_stats.user_id', '=', 'enrollment.user_id')
            ->where('enrollment.class_id', $id)
            ->whereIn('enrollment.status', ['active', 'revoked'])
            ->select([
                'enrollment.id AS enrollment_id', 'enrollment.user_id', 'enrollment.status AS enrollment_status',
                'enrollment.approved_at', 'user.name', 'user.email', 'user.status AS account_status',
            ])
            ->selectRaw('COALESCE(completion.completed_count, 0) AS completed_count')
            ->selectRaw('COALESCE(practice_stats.finished_attempts, 0) AS finished_attempts')
            ->selectRaw('practice_stats.best_score AS best_score');
        // A scalar correlated subquery avoids multiplying rows from several attempts.
        $query->selectSub(
            DB::table('class_practice_attempts as recent')
                ->join('class_practices as practice', 'practice.id', '=', 'recent.class_practice_id')
                ->whereColumn('recent.user_id', 'enrollment.user_id')
                ->where('practice.class_id', $id)
                ->whereIn('recent.status', ['completed', 'timed_out'])
                ->orderByDesc('recent.finished_at')->orderByDesc('recent.id')
                ->limit(1)->select('recent.score_percent'),
            'latest_score'
        );
        return $query;
    }

    /** @param array{q?:string|null,status?:string|null} $filters */
    public function applyEnrollmentFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('enrollment.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function ($nested) use ($term): void {
                $nested->where('user.name', 'like', '%'.$term.'%')
                    ->orWhere('user.email', 'like', '%'.$term.'%');
            });
        }
    }

    /** @param array{q?:string|null,status?:string|null} $filters */
    public function enrollments(LearningClass $class, array $filters, int $publishedMaterials): LengthAwarePaginator
    {
        $query = $this->enrollmentTableQuery($class);
        $this->applyEnrollmentFilters($query, $filters);
        $rows = $query->orderBy('user.name')->orderBy('enrollment.id')->paginate(15)->withQueryString();
        $rows->getCollection()->transform(function ($row) use ($publishedMaterials) {
            $row->completed_count = (int) $row->completed_count;
            $row->finished_attempts = (int) $row->finished_attempts;
            $row->progress_percent = $publishedMaterials === 0 ? 0
                : (int) round($row->completed_count * 100 / $publishedMaterials);
            return $row;
        });
        return $rows;
    }

    /** @return array{enrollment:ClassEnrollment,publishedMaterials:int,completedMaterials:int,percent:int,materials:LengthAwarePaginator,attempts:LengthAwarePaginator} */
    public function participant(LearningClass $class, User $participant): array
    {
        abort_unless($participant->role === 'participant', 404);
        $enrollment = ClassEnrollment::query()->where('class_id', $class->id)
            ->where('user_id', $participant->id)->whereIn('status', ['active', 'revoked'])->firstOrFail();
        $materialsQuery = DB::table('learning_materials as materials')
            ->leftJoin('learning_material_progress as progress', function ($join) use ($participant): void {
                $join->on('progress.material_id', '=', 'materials.id')
                    ->where('progress.user_id', '=', $participant->id);
            })
            ->where('materials.class_id', $class->id)->where('materials.status', 'published');
        $published = (clone $materialsQuery)->count();
        $completed = (clone $materialsQuery)->whereNotNull('progress.completed_at')->count();
        $materials = $materialsQuery->select('materials.id', 'materials.title', 'materials.sort_order', 'progress.completed_at')
            ->orderBy('materials.sort_order')->orderBy('materials.id')
            ->paginate(20, ['*'], 'materials_page')->withQueryString();
        $attempts = ClassPracticeAttempt::query()->with('practice:id,class_id,title')
            ->where('user_id', $participant->id)
            ->whereHas('practice', fn ($query) => $query->where('class_id', $class->id))
            ->whereIn('status', ['completed', 'timed_out'])
            ->orderByDesc('finished_at')->orderByDesc('id')
            ->paginate(15, ['*'], 'attempts_page')->withQueryString();
        return [
            'enrollment' => $enrollment,
            'publishedMaterials' => $published,
            'completedMaterials' => $completed,
            'percent' => $published === 0 ? 0 : (int) round($completed * 100 / $published),
            'materials' => $materials,
            'attempts' => $attempts,
        ];
    }
}
