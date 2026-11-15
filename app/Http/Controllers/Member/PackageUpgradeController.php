<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberPlan;
use App\Models\PackageUpgradeRequest;
use App\Services\PackageUpgradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PackageUpgradeController extends Controller
{
    public function store(Request $request, MemberPlan $plan, PackageUpgradeService $service): RedirectResponse
    {
        $request->validate(['confirmed' => ['required','accepted']]);
        $upgrade = $service->request($request->user(), $plan);
        return redirect()->route('member.packages.request', $upgrade)
            ->with('success','Pengajuan tercatat. Lanjutkan komunikasi dengan admin secara manual.');
    }

    public function show(Request $request, PackageUpgradeRequest $upgrade, PackageUpgradeService $service): mixed
    {
        abort_unless($upgrade->user_id === $request->user()->id, 404);
        return view('member.packages.request', ['upgrade' => $upgrade->load(['sourcePlan','targetPlan','targetVersion']),
            'whatsappUrl' => $service->whatsappUrl($upgrade)]);
    }
}
