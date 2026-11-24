<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use App\Models\MemberPlan;
use App\Models\PackageUpgradeRequest;
use App\Services\MemberPlanService;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    public function about(): View { return $this->show('tentang'); }
    public function contact(): View { return $this->show('kontak'); }
    public function privacy(): View { return config('pilot.mode') ? view('pilot.legal',['document'=>'privacy']) : $this->show('privasi'); }
    public function terms(): View { return config('pilot.mode') ? view('pilot.legal',['document'=>'terms']) : $this->show('syarat-layanan'); }
    public function classes(): View { return $this->show('kelas'); }
    public function packages(MemberPlanService $service): View
    {
        $catalog = MemberPlan::query()->with(['publishedVersion','quizQuotaPolicy'])
            ->whereNotNull('published_version_id')->where('is_archived', false)
            ->orderBy('tier')->orderBy('id')->get();
        $user = Auth::user();
        $active = $user && $user->role === 'participant' && $user->status === 'active';
        $current = $active ? $service->current($user)['plan'] : null;
        $pending = $active ? PackageUpgradeRequest::query()->where('user_id', $user->id)
            ->where('status', 'pending')->latest('id')->first() : null;
        return view('pages.packages', compact('catalog','current','pending'));
    }

    private function show(string $page): View
    {
        abort_unless(in_array($page, ['tentang', 'kontak', 'privasi', 'syarat-layanan', 'kelas', 'paket'], true), 404);
        return view('pages.static', compact('page'));
    }
}
