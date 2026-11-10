<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningClass;
use App\Models\User;
use App\Services\ClassMonitoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassMonitoringController extends Controller
{
    public function index(Request $request, LearningClass $class, ClassMonitoringService $monitoring): View|JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'revoked'])],
            'search.value' => ['nullable', 'string', 'max:100'],
        ]);
        $totals = $monitoring->totals($class);

        // One route: Blade for browsers and Yajra server-side JSON for monitoring AJAX.
        if ($request->ajax() && $request->has('draw')) {
            $term = trim((string) data_get($filters, 'search.value', $filters['q'] ?? ''));
            $status = (string) ($filters['status'] ?? 'all');
            return DataTables::query($monitoring->enrollmentTableQuery($class))
                ->filter(function ($query) use ($monitoring, $status, $term): void {
                    $monitoring->applyEnrollmentFilters($query, ['status' => $status, 'q' => $term]);
                })
                ->addColumn('progress_percent', fn ($row) => $totals['publishedMaterials'] === 0
                    ? 0 : (int) round((int) $row->completed_count * 100 / $totals['publishedMaterials']))
                ->addColumn('detail_url', fn ($row) => route('admin.classes.monitoring.show', [$class, $row->user_id]))
                ->orderColumn('name', 'user.name $1')
                ->orderColumn('enrollment_status', 'enrollment.status $1')
                ->escapeColumns(['*'])
                ->toJson();
        }

        // Plain GET remains usable when JavaScript is disabled or unavailable.
        $participants = $monitoring->enrollments($class, $filters, $totals['publishedMaterials']);
        return view('admin.class-monitoring.index', compact('class', 'filters', 'totals', 'participants'));
    }

    public function show(LearningClass $class, User $user, ClassMonitoringService $monitoring): View
    {
        $data = $monitoring->participant($class, $user);
        return view('admin.class-monitoring.show', [...$data, 'class' => $class, 'participant' => $user]);
    }
}
