<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassPractice;
use App\Models\LearningClass;
use App\Models\Question;
use App\Models\Subject;
use App\Services\ClassPracticeEditorialService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClassPracticeManagementController extends Controller
{
    private function belongs(LearningClass $class, ClassPractice $practice): void
    {
        abort_unless((int)$practice->class_id===(int)$class->id,404);
    }

    private function rules(): array
    {
        return [
            'title'=>['required','string','min:5','max:160'],
            'description'=>['nullable','string','max:2500'],
            'material_id'=>['nullable','integer',Rule::exists('learning_materials','id')],
            'duration_minutes'=>['required','integer','between:5,180'],
            'max_attempts'=>['required','integer','between:1,5'],
            'sort_order'=>['required','integer','between:0,65535'],
            'version_ids'=>['sometimes','array','max:100'],
            'version_ids.*'=>['required','integer','distinct',Rule::exists('question_versions','id')],
        ];
    }

    private function formData(LearningClass $class, ?ClassPractice $practice=null): array
    {
        $selected=$practice ? $practice->items()->with('version.question.topic.subject')->get()->map(function($item) {
            $v=$item->version;
            return ['version_id'=>$item->question_version_id, 'code'=>$v?->question?->code??'—',
                'subject'=>$v?->question?->topic?->subject?->code??'—', 'version'=>$v?->version_number,
                'summary'=>Str::limit($v?->body??'',110)];
        }) : collect();
        return [
            'learningClass'=>$class, 'practice'=>$practice,
            'materials'=>$class->materials()->where('status','published')->orderBy('sort_order')->get(),
            'subjects'=>Subject::query()->where('status','active')->orderBy('sort_order')->get(),
            'selected'=>$selected,
        ];
    }

    public function searchQuestions(Request $request, LearningClass $class): JsonResponse
    {
        Gate::authorize('viewAny',ClassPractice::class);
        $data=$request->validate(['q'=>['nullable','string','max:100'],'subject'=>['nullable','string','max:12']]);
        $query=Question::query()->with(['topic.subject','currentVersion:id,question_id,version_number,body'])
            ->where('status','published')->whereNotNull('current_version_id')
            ->whereHas('currentVersion',fn($v)=>$v->where('review_status','published'))
            ->whereHas('topic',fn($q)=>$q->where('status','active')->whereHas('subject',fn($s)=>$s->where('status','active')));
        if (!empty($data['subject'])) $query->whereHas('topic.subject',fn($q)=>$q->where('code',$data['subject']));
        if (!empty($data['q'])) {
            $term='%'.trim($data['q']).'%';
            $query->where(fn($q)=>$q->where('code','like',$term)
                ->orWhereHas('currentVersion',fn($v)=>$v->where('body','like',$term)));
        }
        return response()->json(['data'=>$query->orderBy('code')->limit(25)->get()->map(fn(Question $q)=>[
            'version_id'=>$q->current_version_id,'code'=>$q->code,'subject'=>$q->topic?->subject?->code,
            'version'=>$q->currentVersion?->version_number,'summary'=>Str::limit($q->currentVersion?->body??'',100),
        ])->values()]);
    }

    public function index(LearningClass $class): View
    {
        Gate::authorize('viewAny',ClassPractice::class);
        $practices=$class->practices()->withCount('items')->orderBy('sort_order')->orderBy('id')->get();
        return view('admin.class-practices.index', ['learningClass'=>$class,'practices'=>$practices]);
    }

    public function create(LearningClass $class): View
    {
        Gate::authorize('create',ClassPractice::class);
        return view('admin.class-practices.form',$this->formData($class));
    }

    public function store(Request $request, LearningClass $class, ClassPracticeEditorialService $service): RedirectResponse
    {
        Gate::authorize('create',ClassPractice::class);
        $data=$request->validate($this->rules());
        $practice=$service->create($class,$data,$request->user());
        return redirect()->route('admin.class-practices.edit',[$class,$practice])->with('success','Draft Latihan Kelas dibuat.');
    }

    public function edit(LearningClass $class, ClassPractice $practice): View
    {
        $this->belongs($class,$practice);
        Gate::authorize('view',$practice);
        $data=$this->formData($class,$practice);
        return view('admin.class-practices.form',$data);
    }

    public function update(Request $request, LearningClass $class, ClassPractice $practice, ClassPracticeEditorialService $service): RedirectResponse
    {
        $this->belongs($class,$practice);
        Gate::authorize('update',$practice);
        $service->update($practice,$request->validate($this->rules()),$request->user());
        return redirect()->route('admin.class-practices.edit',[$class,$practice])->with('success','Draft Latihan Kelas tersimpan.');
    }

    public function publish(Request $request, LearningClass $class, ClassPractice $practice, ClassPracticeEditorialService $service): RedirectResponse
    {
        $this->belongs($class,$practice);
        Gate::authorize('publish',$practice);
        $request->validate(['confirmed_review'=>['required','accepted']]);
        $service->publish($practice,$request->user());
        return redirect()->route('admin.class-practices.edit',[$class,$practice])->with('success','Latihan Kelas diterbitkan.');
    }

    public function archive(Request $request, LearningClass $class, ClassPractice $practice, ClassPracticeEditorialService $service): RedirectResponse
    {
        $this->belongs($class,$practice);
        Gate::authorize('archive',$practice);
        $data=$request->validate(['reason'=>['required','string','min:10','max:500']]);
        $service->archive($practice,$request->user(),$data['reason']);
        return redirect()->route('admin.class-practices.index',$class)->with('success','Latihan diarsipkan. Hasil peserta tidak dihapus.');
    }
}
