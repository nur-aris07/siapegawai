<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\TryoutAttempt;
use App\Services\MemberTryoutService;
use App\Services\MemberSubjectAnalysisService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Read-only progress views over server-scored attempts, scoped to the authenticated participant. */
class MemberHistoryController extends Controller
{
    public function index(Request $request, MemberTryoutService $service, MemberSubjectAnalysisService $analysis): View
    {
        $userId = (int) $request->user()->id;

        // A session whose deadline passed must not remain indefinitely "in progress".
        // Reuse the engine's locked, idempotent finalization rather than recalculating scores here.
        $expired = TryoutAttempt::query()
            ->where('user_id', $userId)
            ->where('status', 'in_progress')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(50)
            ->get();
        foreach ($expired as $attempt) {
            $service->expire($attempt);
        }

        $owned = TryoutAttempt::query()->where('user_id', $userId);
        $finished = (clone $owned)->whereIn('status', ['completed', 'timed_out']);
        $metrics = (clone $finished)->selectRaw(
            'COUNT(*) as sessions_count, AVG(score_percent) as average_score, MAX(score_percent) as best_score, SUM(correct_count) as correct_total'
        )->first();
        $active = (clone $owned)->where('status', 'in_progress')
            ->where('expires_at', '>', now())
            ->with('package:id,code,name')
            ->latest('id')
            ->get();
        $history = (clone $finished)
            ->with('package:id,code,name,status')
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
        $hasMoreExpired = (clone $owned)->where('status', 'in_progress')
            ->where('expires_at', '<=', now())->exists();

        $subjectAnalysis = $analysis->forUser($userId);

        return view('member.tryouts.history', compact('metrics', 'active', 'history', 'hasMoreExpired', 'subjectAnalysis'));
    }
}
