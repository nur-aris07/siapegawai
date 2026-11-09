<?php
namespace App\Http\Controllers\Questions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Questions\QuestionContentRequest;
use App\Models\Question;
use App\Models\Subject;
use App\Services\QuestionEditorialService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class QuestionManagementController extends Controller
{
    private function subjects(?Question $editing = null)
    {
        $subjectId = $editing?->topic?->subject_id;
        return Subject::query()->with(['topics'=>function($q) use ($editing) {
                $q->where(fn($where)=>$where->where('status','active')
                    ->when($editing, fn($or)=>$or->orWhere('id',$editing->topic_id)))
                  ->orderBy('sort_order')->orderBy('name');
            }])
            ->where(fn($q)=>$q->where('status','active')->when($subjectId,fn($or)=>$or->orWhere('id',$subjectId)))
            ->orderBy('sort_order')->orderBy('code')->get();
    }

    // Satu URL: HTML biasa dan JSON server-side Yajra melalui request AJAX.
    public function index(Request $request): mixed
    {
        Gate::authorize('viewAny', Question::class);
        $query = Question::query()
            ->with(['latestVersion', 'currentVersion:id,version_number'])
            ->join('topics', 'topics.id', '=', 'questions.topic_id')
            ->join('subjects', 'subjects.id', '=', 'topics.subject_id')
            ->select('questions.*', 'subjects.code as subject_code', 'topics.name as topic_name');
        if (in_array($request->query('status'), ['draft','published','archived'], true)) {
            $query->where('questions.status', $request->query('status'));
        }
        if (in_array($request->query('subject'), ['TWK','TIU','TKP','SKB'], true)) {
            $query->where('subjects.code', $request->query('subject'));
        }
        if ($request->ajax()) {
            // Hindari length=-1 (mengunduh seluruh bank melalui satu request).
            $requestedLength = filter_var($request->input('length', 10), FILTER_VALIDATE_INT);
            $request->merge(['length' => min(50, max(1, $requestedLength === false ? 10 : $requestedLength))]);
            return DataTables::eloquent($query)
                ->filter(function ($builder) use ($request) {
                    $term = $request->input('search.value');
                    if (!is_string($term) || trim($term) === '') return;
                    $term = mb_substr(trim($term), 0, 100);
                    $like = '%'.$term.'%';
                    $builder->where(function ($q) use ($like) {
                        $q->where('questions.code', 'like', $like)
                            ->orWhere('subjects.code', 'like', $like)
                            ->orWhere('topics.name', 'like', $like)
                            ->orWhereHas('versions', fn($v) => $v->where('body', 'like', $like));
                    });
                })
                ->addColumn('summary', fn(Question $q)=>Str::limit($q->latestVersion?->body ?? '', 95))
                ->addColumn('active_version_number', fn(Question $q)=>$q->currentVersion?->version_number)
                ->addColumn('pending_draft_version_number', fn(Question $q)=>$q->status === 'published' && $q->latestVersion?->review_status === 'draft' ? $q->latestVersion->version_number : null)
                ->addColumn('detail_url', fn(Question $q)=>route('questions.admin.edit', $q))
                ->filterColumn('subject_code', fn($q,$value)=>$q->where('subjects.code','like','%'.$value.'%'))
                ->filterColumn('topic_name', fn($q,$value)=>$q->where('topics.name','like','%'.$value.'%'))
                ->orderColumn('subject_code', 'subjects.code $1')
                ->orderColumn('topic_name', 'topics.name $1')
                ->orderColumn('code', 'questions.code $1')
                ->orderColumn('status', 'questions.status $1')
                ->orderColumn('created_at', 'questions.created_at $1')
                ->escapeColumns(['*'])
                ->toJson();
        }
        return view('questions.admin.index', [
            'subjects'=>$this->subjects(),
            'total'=>Question::query()->count(),
            'drafts'=>Question::query()->where('status','draft')->count(),
            'published'=>Question::query()->where('status','published')->count(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Question::class);
        return view('questions.admin.create', ['subjects'=>$this->subjects()]);
    }

    public function store(QuestionContentRequest $request, QuestionEditorialService $service): RedirectResponse
    {
        Gate::authorize('create', Question::class);
        $question = $service->create($request->validated(), $request->user());
        return redirect()->route('questions.admin.edit', $question)->with('success', 'Draft soal dibuat. Tinjau substansi sebelum menerbitkan.');
    }

    public function edit(Question $question): View
    {
        Gate::authorize('view', $question);
        $question->load(['topic.subject', 'latestVersion.options', 'currentVersion']);
        return view('questions.admin.edit', [
            'question'=>$question,
            'version'=>$question->latestVersion,
            'subjects'=>$this->subjects($question),
        ]);
    }

    public function update(QuestionContentRequest $request, Question $question, QuestionEditorialService $service): RedirectResponse
    {
        Gate::authorize('update', $question);
        $result = $service->saveDraft($question, $request->validated(), $request->user());
        return redirect()->route('questions.admin.edit', $question)->with(
            $result === 'unchanged' ? 'status' : 'success',
            $result === 'unchanged'
                ? 'Tidak ada perubahan konten. Tidak dibuat versi baru.'
                : 'Draft soal tersimpan. Versi yang sudah terbit tetap utuh.',
        );
    }

    public function publish(Request $request, Question $question, QuestionEditorialService $service): RedirectResponse
    {
        Gate::authorize('publish', $question);
        $request->validate(['confirmed_review'=>['required','accepted']]);
        $service->publish($question, $request->user());
        return redirect()->route('questions.admin.edit', $question)->with('success', 'Soal berhasil diterbitkan setelah konfirmasi review.');
    }

    public function discardDraft(Request $request, Question $question, QuestionEditorialService $service): RedirectResponse
    {
        Gate::authorize('discardDraft', $question);
        $data = $request->validate([
            'confirmed_discard' => ['required', 'accepted'],
            'discard_reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $service->discardDraft($question, $request->user(), $data['discard_reason']);
        return redirect()->route('questions.admin.edit', $question)
            ->with('success', 'Draft revisi dibatalkan. Versi yang telah terbit tetap aktif.');
    }

    public function archive(Request $request, Question $question, QuestionEditorialService $service): RedirectResponse
    {
        Gate::authorize('archive', $question);
        $request->validate(['archive_reason'=>['required','string','min:10','max:500']]);
        $service->archive($question, $request->user(), $request->string('archive_reason')->toString());
        return redirect()->route('questions.admin.index')->with('success', 'Soal telah diarsipkan. Riwayat versinya tetap tersimpan.');
    }
}
