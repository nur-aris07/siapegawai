<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberPlan;
use App\Models\PackageUpgradeRequest;
use App\Services\PackageCatalogService;
use App\Services\PackageUpgradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class PackageCatalogController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()?->role === 'superadmin' && $request->user()?->status === 'active', 403);
    }

    public function index(Request $request, PackageCatalogService $catalog): mixed
    {
        $this->guard($request);
        if ($request->ajax()) {
            return DataTables::eloquent(MemberPlan::query()->with(['publishedVersion', 'quizQuotaPolicy'])->select('member_plans.*'))
                ->addColumn('title', fn (MemberPlan $plan) => $plan->publishedVersion?->title ?? $plan->name)
                ->addColumn('draft', fn (MemberPlan $plan) => $plan->versions()->where('status','draft')->exists())
                ->addColumn('url', fn (MemberPlan $plan) => route('admin.packages.show', $plan))
                ->filterColumn('code', fn ($q, $term) => $q->where('member_plans.code','like','%'.$term.'%'))
                ->filterColumn('name', fn ($q, $term) => $q->where('member_plans.name','like','%'.$term.'%'))
                ->orderColumn('name','member_plans.name $1')->orderColumn('code','member_plans.code $1')
                ->orderColumn('tier','member_plans.tier $1')->escapeColumns(['*'])->toJson();
        }
        return view('admin.packages.index', ['plans' => MemberPlan::query()->with('publishedVersion')->orderBy('tier')->get(),
            'requests' => PackageUpgradeRequest::query()
            ->with(['participant','sourcePlan','targetPlan','targetVersion'])
            ->where('status','pending')->orderBy('created_at')->limit(100)->get()]);
    }

    public function ordering(Request $request, PackageCatalogService $catalog): mixed
    {
        $this->guard($request);
        return view('admin.packages.ordering', [
            'plans' => MemberPlan::query()->with('publishedVersion')->orderBy('tier')->orderBy('id')->get(),
            'fingerprint' => $catalog->orderFingerprint(),
        ]);
    }

    public function saveOrdering(Request $request, PackageCatalogService $catalog): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1', 'max:255'],
            'order.*' => ['required', 'integer', 'distinct', 'min:1'],
            'fingerprint' => ['required', 'string', 'size:64'],
            'confirmed' => ['required', 'accepted'],
        ]);
        $changed = $catalog->reorder($request->user(), $data['order'], $data['fingerprint']);
        return redirect()->route('admin.packages.ordering')->with('success',
            $changed ? 'Urutan paket berhasil disimpan.' : 'Urutan paket tidak berubah.');
    }

    public function show(Request $request, MemberPlan $plan, PackageCatalogService $catalog): mixed
    {
        $this->guard($request);
        $plan->load(['publishedVersion', 'quizQuotaPolicy']);
        $draft = $plan->versions()->where('status', 'draft')->first();

        // Preserve the existing JSON contract for explicit API consumers.
        // Normal navigation goes to a full-page, server-rendered editor.
        if ($request->wantsJson()) {
            return response()->json(['id' => $plan->id, 'code' => $plan->code, 'name' => $plan->name,
                'availability' => $plan->availability, 'is_archived' => $plan->is_archived,
                'published' => $plan->publishedVersion ? ['title' => $plan->publishedVersion->title,
                    'summary' => $plan->publishedVersion->summary, 'version' => $plan->publishedVersion->version_no] : null,
                'draft' => $draft ? ['title' => $draft->title, 'summary' => $draft->summary,
                    'benefits' => implode("\n", $draft->benefits ?? []), 'version' => $draft->version_no,
                    'fingerprint' => $catalog->fingerprint($draft)] : null,
                'urls' => [
                    'update' => route('admin.packages.draft.update', $plan),
                    'revision' => route('admin.packages.revision', $plan),
                    'publish' => route('admin.packages.publish', $plan),
                    'discard' => route('admin.packages.discard', $plan),
                    'availability' => route('admin.packages.availability', $plan),
                    'preview' => route('admin.packages.preview', $plan),
                ]]);
        }

        return view('admin.packages.show', [
            'plan' => $plan,
            'published' => $plan->publishedVersion,
            'draft' => $draft,
            'draftFingerprint' => $draft ? $catalog->fingerprint($draft) : null,
            'versions' => $plan->versions()->orderByDesc('version_no')->get(),
        ]);
    }

    public function store(Request $request, PackageCatalogService $catalog): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'code' => ['required','string','regex:/^[A-Z][A-Z0-9_]{1,31}$/','unique:member_plans,code'],
            'name' => ['required','string','max:80'],
            'is_paid' => ['required','boolean'], 'summary' => ['required','string','max:2000'],
            'benefits' => ['nullable','string','max:2500'],
        ]);
        $plan = $catalog->create($request->user(), $data);
        return redirect()->route('admin.packages.index')->with('success','Paket '.$plan->code.' dibuat sebagai draft.');
    }

    private function draftFields(Request $request): array
    {
        return $request->validate(['title' => ['required','string','max:120'],
            'summary' => ['required','string','max:2000'], 'benefits' => ['nullable','string','max:2500'],
            'fingerprint' => ['required','string','size:64']]);
    }

    public function preview(Request $request, MemberPlan $plan, PackageCatalogService $catalog): mixed
    {
        $this->guard($request);
        $data = $this->draftFields($request);
        $draft = $plan->versions()->where('status','draft')->firstOrFail();
        if (!hash_equals($catalog->fingerprint($draft), $data['fingerprint'])) {
            return back()->withErrors(['fingerprint' => 'Draft telah berubah. Muat ulang.']);
        }
        return view('admin.packages.preview', ['plan' => $plan,'title' => $data['title'],
            'summary' => $data['summary'],'benefits' => $catalog->benefitLines($data['benefits'] ?? ''),
            'published' => $plan->publishedVersion, 'fingerprint' => $data['fingerprint']]);
    }

    public function updateDraft(Request $request, MemberPlan $plan, PackageCatalogService $catalog): RedirectResponse
    {
        $this->guard($request);
        $catalog->updateDraft($request->user(), $plan, $this->draftFields($request));
        return back()->with('success','Draft diperbarui. Versi publik tetap tidak berubah.');
    }

    public function revision(Request $request, MemberPlan $plan, PackageCatalogService $catalog): RedirectResponse
    {
        $this->guard($request);
        $request->validate(['confirmed' => ['required','accepted']]);
        $catalog->startRevision($request->user(), $plan);
        return back()->with('success','Draft revisi dibuat; versi publik tetap aktif.');
    }

    public function publish(Request $request, MemberPlan $plan, PackageCatalogService $catalog): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['confirmed' => ['required','accepted'], 'fingerprint' => ['required','string','size:64']]);
        $catalog->publish($request->user(), $plan, $data['fingerprint']);
        return back()->with('success','Versi baru diterbitkan.');
    }

    public function discard(Request $request, MemberPlan $plan, PackageCatalogService $catalog): RedirectResponse
    {
        $this->guard($request);
        $request->validate(['confirmed' => ['required','accepted']]);
        $catalog->discard($request->user(), $plan);
        return back()->with('success','Draft dibatalkan tanpa mengubah versi publik.');
    }

    public function availability(Request $request, MemberPlan $plan, PackageCatalogService $catalog): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['availability' => ['required', Rule::in(['planned','requestable','closed','archived'])],
            'confirmed' => ['required','accepted']]);
        $catalog->availability($request->user(), $plan, $data['availability']);
        return back()->with('success','Ketersediaan paket diperbarui. Keanggotaan existing tidak dicabut.');
    }

    public function requests(Request $request): mixed
    {
        $this->guard($request);
        $query = PackageUpgradeRequest::query()->with(['participant','sourcePlan','targetPlan','targetVersion']);
        return view('admin.packages.requests', ['requests' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function decide(Request $request, PackageUpgradeRequest $upgrade, PackageUpgradeService $service): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['decision' => ['required',Rule::in(['completed','rejected'])],
            'reason' => ['required','string','min:12','max:500'], 'confirmed' => ['required','accepted'],
            'manual_verified' => ['required_if:decision,completed','accepted']]);
        $service->decide($request->user(), $upgrade, $data['decision'], $data['reason'], !empty($data['manual_verified']));
        return back()->with('success','Pengajuan telah diproses secara manual dan dicatat di audit.');
    }
}
