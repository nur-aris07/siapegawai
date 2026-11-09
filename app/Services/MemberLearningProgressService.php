<?php

namespace App\Services;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class MemberLearningProgressService
{
    /**
     * @param list<int> $classIds
     * @return array<int, array{total:int,completed:int,percent:int}>
     */
    public function summaries(array $classIds, int $userId): array
    {
        $ids = array_values(array_unique(array_map('intval', $classIds)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('learning_materials as materials')
            ->leftJoin('learning_material_progress as progress', function (JoinClause $join) use ($userId): void {
                $join->on('progress.material_id', '=', 'materials.id')
                    ->where('progress.user_id', '=', $userId);
            })
            ->whereIn('materials.class_id', $ids)
            ->where('materials.status', 'published')
            ->groupBy('materials.class_id')
            ->select('materials.class_id')
            ->selectRaw('COUNT(materials.id) as total')
            ->selectRaw('COUNT(progress.id) as completed')
            ->get();

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = ['total' => 0, 'completed' => 0, 'percent' => 0];
        }
        foreach ($rows as $row) {
            $total = (int) $row->total;
            $completed = (int) $row->completed;
            $result[(int) $row->class_id] = [
                'total' => $total,
                'completed' => $completed,
                'percent' => $total === 0 ? 0 : (int) round($completed * 100 / $total),
            ];
        }
        return $result;
    }
}
