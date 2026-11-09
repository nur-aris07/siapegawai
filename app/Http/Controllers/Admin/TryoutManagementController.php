<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TryoutPackage;
use App\Services\TryoutEditorialService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class TryoutManagementController extends Controller
{
    private function payloadRules(?TryoutPackage $package = null): array
    {
        return [
            'code'=>['required','string','max:48','regex:/^[A-Z][A-Z0-9-]*$/',Rule::unique('tryout_packages','code')->ignore($package?->id)],
            'name'=>['required','string','min:5','max:180'],
            'description'=>['nullable','string','max:2500'],
            'duration_minutes'=>['required','integer','between:5,360'],
            'max_attempts'=>['required','integer','between:1,20'],
            'version_ids'=>['sometimes','array','max:100'],
            'version_ids.*'=>['required','integer','distinct',Rule::exists('question_versions','id')],
        ];
    }

    public function index(Request $request): mixed
    {
        Gate::authorize('viewAny',TryoutPackage::class);
        $query=TryoutPackage::query()->withCount('items');
        if (in_array($request->query('status'),['draft','published','archived'],true)) $query->where('status',$request->query('status'));
        if ($request->ajax()) {
            $length=filter_var($request->input('length',10),FILTER_VALIDATE_INT);
            $request->merge(['length'=>min(50,max(1,$length===false?10:$length))]);
            return DataTables::eloquent($query)
                ->filter(function ($q) use($request) {
                    $s=$request->input('search.value');
                    if (!is_string($s) || trim($s)==='') return;
                    $s='%'.mb_substr(trim($s),0,90).'%';
                    $q->where(fn($sub)=>$sub->where('code','like',$s)->orWhere('name','like',$s));
                })
                ->addColumn('detail_url',fn(TryoutPackage $package)=>route('tryouts.admin.edit',$package))
                ->escapeColumns(['*'])->toJson();
        }
        return view('admin.tryouts.index',[
            'total'=>TryoutPackage::query()->count(),
            'drafts'=>TryoutPackage::query()->where('status','draft')->count(),
            'published'=>TryoutPackage::query()->where('status','published')->count(),
        ]);
    }

    public function searchQuestions(Request $request): JsonResponse
    {
        Gate::authorize('viewAny',TryoutPackage::class);
        $data=$request->validate(['q'=>['nullable','string','max:100'],'subject'=>['nullable','string','max:12']]);
        $query=Question::query()->with(['topic.subject','currentVersion:id,question_id,version_number,body'])
            ->where('status','published')->whereNotNull('current_version_id')
            ->whereHas('topic',fn($q)=>$q->where('status','active')->whereHas('subject',fn($s)=>$s->where('status','active')));
        if (!empty($data['subject'])) {
            $query->whereHas('topic.subject',fn($s)=>$s->where('code',$data['subject']));
        }
        if (!empty($data['q'])) {
            $term='%'.trim($data['q']).'%';
            $query->where(fn($q)=>$q->where('code','like',$term)
                ->orWhereHas('currentVersion',fn($v)=>$v->where('body','like',$term)));
        }
        return response()->json(['data'=>$query->orderBy('code')->limit(25)->get()->map(fn(Question $q)=>[
            'version_id'=>$q->current_version_id,'code'=>$q->code,
            'subject'=>$q->topic?->subject?->code, 'version'=>$q->currentVersion?->version_number,
            'summary'=>Str::limit($q->currentVersion?->body??'',100),
        ])->values()]);
    }

    public function create(): View
    {
        Gate::authorize('create',TryoutPackage::class);
        return view('admin.tryouts.create',[
            'subjects'=>Subject::query()->where('status','active')->orderBy('sort_order')->get(),
            'selected'=>collect(),
        ]);
    }

    public function store(Request $request, TryoutEditorialService $service): RedirectResponse
    {
        Gate::authorize('create',TryoutPackage::class);
        $request->merge(['code'=>strtoupper(trim((string)$request->input('code')))]);
        $data=$request->validate($this->payloadRules());
        $package=$service->create($data,$request->user());
        return redirect()->route('tryouts.admin.edit',$package)->with('success','Draft tryout dibuat. Pilihan soal belum bisa dikerjakan peserta.');
    }

    public function edit(TryoutPackage $package): View
    {
        Gate::authorize('view',$package);
        $package->load(['items.version.question.topic.subject']);
        return view('admin.tryouts.edit',[
            'package'=>$package,
            'selected'=>$package->items->map(function($item) {
                $v=$item->version;
                return ['version_id'=>$item->question_version_id,'code'=>$v?->question?->code??'—',
                    'subject'=>$v?->question?->topic?->subject?->code??'—', 'version'=>$v?->version_number,
                    'summary'=>Str::limit($v?->body??'',110)];
            }),
            'subjects'=>Subject::query()->where('status','active')->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, TryoutPackage $package, TryoutEditorialService $service): RedirectResponse
    {
        Gate::authorize('update',$package);
        $request->merge(['code'=>strtoupper(trim((string)$request->input('code')))]);
        $service->update($package,$request->validate($this->payloadRules($package)),$request->user());
        return redirect()->route('tryouts.admin.edit',$package)->with('success','Draft tryout tersimpan; versi soal pilihan tetap terkunci.');
    }

    public function publish(Request $request, TryoutPackage $package, TryoutEditorialService $service): RedirectResponse
    {
        Gate::authorize('publish',$package);
        $request->validate(['confirmed_review'=>['required','accepted']]);
        $service->publish($package,$request->user());
        return redirect()->route('tryouts.admin.edit',$package)->with('success','Template tryout diterbitkan. Engine peserta belum diaktifkan.');
    }

    public function archive(Request $request, TryoutPackage $package, TryoutEditorialService $service): RedirectResponse
    {
        Gate::authorize('archive',$package);
        $data=$request->validate(['reason'=>['required','string','min:10','max:500']]);
        $service->archive($package,$request->user(),$data['reason']);
        return redirect()->route('tryouts.admin.edit',$package)->with('success','Tryout diarsipkan; relasi ke versi soal tetap tersimpan.');
    }
}
