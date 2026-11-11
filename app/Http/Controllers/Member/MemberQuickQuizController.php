<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\QuickQuiz;
use App\Models\QuickQuizAttempt;
use App\Services\QuickQuizAttemptService;
use App\Services\QuickQuizQuotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class MemberQuickQuizController extends Controller
{
    public function index(Request $request, QuickQuizQuotaService $quota): View
    {
        $quizzes = QuickQuiz::query()->where('status','published')
            ->whereHas('currentVersion',fn($q)=>$q->where('status','published'))
            ->with(['currentVersion'=>fn($q)=>$q->withCount('items')])->orderByDesc('id')->paginate(12);
        $quotaToday = $quota->remaining($request->user());
        $active = QuickQuizAttempt::query()->where('user_id',$request->user()->id)->where('status','active')
            ->with('version.quiz')->latest('id')->first();
        return view('member.quick-quizzes.index',compact('quizzes','quotaToday','active'));
    }

    public function show(Request $request, QuickQuiz $quiz, QuickQuizQuotaService $quota): View
    {
        abort_unless($quiz->status==='published' && $quiz->currentVersion?->status==='published',404);
        $quiz->load('currentVersion');
        $count=$quiz->currentVersion->items()->count();
        $quotaToday=$quota->remaining($request->user());
        $active=QuickQuizAttempt::query()->where('user_id',$request->user()->id)->where('status','active')
            ->with('version.quiz')->latest('id')->first();
        $startKey=Str::random(40);
        return view('member.quick-quizzes.show',compact('quiz','count','quotaToday','active','startKey'));
    }

    public function start(Request $request, QuickQuiz $quiz, QuickQuizAttemptService $service): RedirectResponse
    {
        $validated=$request->validate(['start_key'=>['required','string','regex:/^[A-Za-z0-9_-]{12,80}$/D']]);
        $attempt=$service->start($request->user(),$quiz,$validated['start_key']);
        return redirect()->route($attempt->status==='active'?'member.quick-quizzes.take':'member.quick-quizzes.result',$attempt);
    }

    private function owned(Request $request, QuickQuizAttempt $attempt): void
    {
        abort_unless((int)$attempt->user_id===(int)$request->user()->id,403);
    }

    public function take(Request $request, QuickQuizAttempt $attempt, QuickQuizAttemptService $service): View|RedirectResponse
    {
        $this->owned($request,$attempt);
        $attempt=$service->read($attempt);
        if ($attempt->status!=='active') return redirect()->route('member.quick-quizzes.result',$attempt);
        $attempt->load('version.quiz');
        $item=$service->currentItem($attempt);
        abort_unless($item,500);
        $answer=$attempt->answers()->where('quick_quiz_version_item_id',$item->id)->first();
        $correct=$attempt->phase==='feedback' ? $item->version->options->firstWhere('is_correct',true) : null;
        return view('member.quick-quizzes.take',compact('attempt','item','answer','correct'));
    }

    public function answer(Request $request, QuickQuizAttempt $attempt, QuickQuizAttemptService $service): RedirectResponse
    {
        $this->owned($request,$attempt);
        $data=$request->validate(['item_id'=>['required','integer'],'answer'=>['present','nullable','string','in:A,B,C,D,E']]);
        $service->answer($attempt,(int)$data['item_id'],$data['answer']??null);
        return redirect()->route('member.quick-quizzes.take',$attempt);
    }

    public function next(Request $request, QuickQuizAttempt $attempt, QuickQuizAttemptService $service): RedirectResponse
    {
        $this->owned($request,$attempt);
        $attempt=$service->next($attempt);
        return redirect()->route($attempt->status==='active'?'member.quick-quizzes.take':'member.quick-quizzes.result',$attempt);
    }

    public function result(Request $request, QuickQuizAttempt $attempt, QuickQuizAttemptService $service): View|RedirectResponse
    {
        $this->owned($request,$attempt);
        $attempt=$service->read($attempt);
        if ($attempt->status==='active') return redirect()->route('member.quick-quizzes.take',$attempt);
        $attempt->load('version.quiz');
        // Only locked questions; an interrupted/abandoned session does not leak unseen keys.
        $answers=$attempt->answers()->with('item.version.options')->orderBy('id')->get();
        return view('member.quick-quizzes.result',compact('attempt','answers'));
    }

    public function history(Request $request): View
    {
        $attempts=QuickQuizAttempt::query()->where('user_id',$request->user()->id)
            ->where('status','!=','active')->with('version.quiz')->latest('id')->paginate(15);
        return view('member.quick-quizzes.history',compact('attempts'));
    }
}
