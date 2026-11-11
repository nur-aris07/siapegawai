<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuickQuiz;
use App\Models\Subject;
use App\Services\QuickQuizEditorialService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class QuickQuizManagementController extends Controller
{
    private function rules(): array
    {
        return [
            'title'=>['required','string','min:5','max:180'],
            'description'=>['nullable','string','max:2500'],
            // Engineering safety bound, not a package benefit or quota configuration.
            'seconds_per_question'=>['required','integer','between:5,180'],
            'version_ids'=>['sometimes','array','max:40'],
            'version_ids.*'=>['required','integer','distinct',Rule::exists('question_versions','id')],
        ];
    }

    public function index(Request $request): mixed
    {
        Gate::authorize('viewAny',QuickQuiz::class);
        $query=QuickQuiz::query()->with([
            'currentVersion:id,title,quick_quiz_id,version_number,seconds_per_question',
            'versions'=>fn ($q)=>$q->where('status','draft')->select(['id','quick_quiz_id','title']),
        ])->withCount('versions');
        $data=$request->validate(['status'=>['nullable',Rule::in(['draft','published','archived'])]]);
        if (!empty($data['status'])) $query->where('status',$data['status']);
        if ($request->ajax()) {
            $length=filter_var($request->input('length',10),FILTER_VALIDATE_INT);
            $request->merge(['length'=>min(50,max(1,$length===false?10:$length))]);
            return DataTables::eloquent($query)
                ->filter(function ($q) use ($request) {
                    $term=trim((string)$request->input('search.value',''));
                    if ($term==='') return;
                    $like='%'.mb_substr($term,0,90).'%';
                    $q->where(fn($sub)=>$sub->where('code','like',$like)
                        ->orWhereHas('currentVersion',fn($v)=>$v->where('title','like',$like))
                        ->orWhereHas('versions',fn($v)=>$v->where('title','like',$like)));
                })
                ->addColumn('title', fn(QuickQuiz $quiz) => $quiz->currentVersion?->title ?? $quiz->versions->first()?->title ?? '—')
                ->addColumn('edition',fn(QuickQuiz $quiz)=>$quiz->currentVersion?->version_number)
                ->addColumn('detail_url',fn(QuickQuiz $quiz)=>route('admin.quick-quizzes.edit',$quiz))
                ->escapeColumns(['*'])->toJson();
        }
        return view('admin.quick-quizzes.index',[
            'total'=>QuickQuiz::query()->count(),
            'drafts'=>QuickQuiz::query()->where('status','draft')->count(),
            'published'=>QuickQuiz::query()->where('status','published')->count(),
        ]);
    }

    public function searchQuestions(Request $request): JsonResponse
    {
        Gate::authorize('viewAny',QuickQuiz::class);
        $data=$request->validate(['q'=>['nullable','string','max:100'],'subject'=>['nullable','string','max:12']]);
        $query=Question::query()->with(['topic.subject','currentVersion.options'])
            ->where('status','published')->whereNotNull('current_version_id')
            ->whereHas('currentVersion',fn($v)=>$v->where('review_status','published')->whereNotNull('published_at')
                ->whereNotNull('explanation')
                ->whereHas('options',fn($o)=>$o->where('is_correct',true)))
            ->whereHas('topic',fn($q)=>$q->where('status','active')->whereHas('subject',fn($s)=>$s->where('status','active')));
        if (!empty($data['subject'])) $query->whereHas('topic.subject',fn($s)=>$s->where('code',$data['subject']));
        if (!empty($data['q'])) {
            $term='%'.trim($data['q']).'%';
            $query->where(fn($q)=>$q->where('code','like',$term)
                ->orWhereHas('currentVersion',fn($v)=>$v->where('body','like',$term)));
        }
        return response()->json(['data'=>$query->orderBy('code')->limit(30)->get()
            ->filter(fn(Question $q)=>$q->currentVersion && $q->currentVersion->options->count()>=4 &&
                $q->currentVersion->options->count()<=5 &&
                $q->currentVersion->options->where('is_correct',true)->count()===1 &&
                trim((string)$q->currentVersion->explanation)!=='' &&
                !$q->currentVersion->options->contains(fn($o)=>$o->score_weight!==null))
            ->map(fn(Question $q)=>[
                'version_id'=>$q->current_version_id,'code'=>$q->code,
                'subject'=>$q->topic?->subject?->code,
                'version'=>$q->currentVersion?->version_number,
                'summary'=>Str::limit($q->currentVersion?->body??'',95),
            ])->values()]);
    }

    private function editorData(?QuickQuiz $quiz=null): array
    {
        $draft=$quiz?->draftVersion();
        $selected=$draft ? $draft->items()->with('version.question.topic.subject')->get()->map(function ($item) {
            $v=$item->version;
            return ['version_id'=>$v->id,'code'=>$v->question?->code??'',
                'subject'=>$v->question?->topic?->subject?->code??'',
                'version'=>$v->version_number,'summary'=>Str::limit($v->body,95)];
        }) : collect();
        return ['quiz'=>$quiz,'draft'=>$draft,'selected'=>$selected,
            'subjects'=>Subject::query()->where('status','active')->orderBy('sort_order')->get()];
    }

    public function create(): View
    {
        Gate::authorize('create',QuickQuiz::class);
        return view('admin.quick-quizzes.editor',$this->editorData());
    }

    public function store(Request $request, QuickQuizEditorialService $editor): RedirectResponse
    {
        Gate::authorize('create',QuickQuiz::class);
        $request->merge(['code'=>strtoupper(trim((string)$request->input('code','')))]);
        $data=$request->validate(array_merge($this->rules(),[
            'code'=>['required','string','max:48','regex:/^[A-Za-z][A-Za-z0-9-]*$/',Rule::unique('quick_quizzes','code')],
        ]));
        $quiz=$editor->create($data,$request->user());
        return redirect()->route('admin.quick-quizzes.edit',$quiz)->with('success','Draft Quiz Kilat dibuat; belum dapat dikerjakan peserta.');
    }

    public function edit(QuickQuiz $quiz): View
    {
        Gate::authorize('view',$quiz);
        $quiz->load('currentVersion');
        return view('admin.quick-quizzes.editor',$this->editorData($quiz));
    }

    public function update(Request $request,QuickQuiz $quiz,QuickQuizEditorialService $editor): RedirectResponse
    {
        Gate::authorize('update',$quiz);
        $editor->update($quiz,$request->validate($this->rules()),$request->user());
        return redirect()->route('admin.quick-quizzes.edit',$quiz)->with('success','Draft Quiz Kilat tersimpan.');
    }

    public function startRevision(Request $request,QuickQuiz $quiz,QuickQuizEditorialService $editor): RedirectResponse
    {
        Gate::authorize('update',$quiz);
        $editor->startRevision($quiz,$request->user());
        return redirect()->route('admin.quick-quizzes.edit',$quiz)->with('success','Draft revisi dibuat tanpa mengubah versi terbit.');
    }

    public function publish(Request $request,QuickQuiz $quiz,QuickQuizEditorialService $editor): RedirectResponse
    {
        Gate::authorize('publish',$quiz);
        $request->validate(['confirmed_review'=>['required','accepted']]);
        $editor->publish($quiz,$request->user());
        return redirect()->route('admin.quick-quizzes.edit',$quiz)->with('success','Versi Quiz Kilat diterbitkan; pengerjaan peserta menunggu kuota terverifikasi.');
    }

    public function archive(Request $request,QuickQuiz $quiz,QuickQuizEditorialService $editor): RedirectResponse
    {
        Gate::authorize('archive',$quiz);
        $data=$request->validate(['reason'=>['required','string','min:10','max:500']]);
        $editor->archive($quiz,$request->user(),$data['reason']);
        return redirect()->route('admin.quick-quizzes.index')->with('success','Quiz Kilat diarsipkan tanpa menghapus versi.');
    }
}
