<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberPlan;
use App\Services\QuickQuizQuotaAdministrationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuickQuizQuotaController extends Controller
{
    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->role === 'superadmin' && $request->user()?->status === 'active', 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeManager($request);
        $plans = MemberPlan::query()->with('quizQuotaPolicy')->orderBy('tier')->get();
        return view('admin.qq-quota.index', compact('plans'));
    }

    public function update(Request $request, MemberPlan $plan, QuickQuizQuotaAdministrationService $service): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'active', 'disabled'])],
            'daily_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'unlimited' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'confirmed' => ['required', 'accepted'],
        ]);
        // Inputs are Asia/Jakarta wall-clock, stored in UTC.
        foreach (['starts_at', 'ends_at'] as $key) {
            $data[$key] = !empty($data[$key])
                ? CarbonImmutable::parse($data[$key], 'Asia/Jakarta')->utc()->toDateTimeString() : null;
        }
        $service->savePlanPolicy($request->user(), $plan, $data);
        return back()->with('success', 'Kuota paket '.$plan->name.' diperbarui. Tidak ada grant QQ yang dibuat.');
    }
}
