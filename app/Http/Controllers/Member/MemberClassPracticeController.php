<?php
namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ClassPractice;
use App\Models\ClassPracticeAttempt;
use App\Models\LearningClass;
use App\Services\ClassPracticeAttemptService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberClassPracticeController extends Controller
{
    private function eligible(Request $request, LearningClass $class, ClassPracticeAttemptService $service): void
    {
        $service->assertEnrolled((int)$class->id,(int)$request->user()->id);
    }

    private function scoped(LearningClass $class, ClassPractice $practice): void
    {
        abort_unless((int)$practice->class_id===(int)$class->id,404);
    }

    private function owned(Request $request, LearningClass $class, ClassPracticeAttempt $attempt): void
    {
        abort_unless((int)$attempt->user_id===(int)$request->user()->id,403);
        abort_unless((int)$attempt->practice->class_id===(int)$class->id,404);
    }

    public function index(Request $request, LearningClass $class, ClassPracticeAttemptService $service): View
    {
        $this->eligible($request,$class,$service);
        $practices=$class->practices()->where('status','published')->withCount('items')->orderBy('sort_order')->orderBy('id')->get();
        $attempts=ClassPracticeAttempt::query()->where('user_id',$request->user()->id)
            ->whereHas('practice',fn($q)=>$q->where('class_id',$class->id))
            ->with('practice:id,class_id,title,status')->latest('id')->limit(100)->get();
        return view('member.class-practices.index',compact('class','practices','attempts'));
    }

    public function show(Request $request, LearningClass $class, ClassPractice $practice, ClassPracticeAttemptService $service): View
    {
        $this->scoped($class,$practice);
        $this->eligible($request,$class,$service);
        abort_unless($practice->status==='published',404);
        $practice->loadCount('items');
        $attempts=$practice->attempts()->where('user_id',$request->user()->id)->latest('id')->get();
        return view('member.class-practices.show',compact('class','practice','attempts'));
    }

    public function start(Request $request, LearningClass $class, ClassPractice $practice, ClassPracticeAttemptService $service): RedirectResponse
    {
        $this->scoped($class,$practice);
        $attempt=$service->start($request->user(),$practice);
        return redirect()->route('member.class-practices.take',[$class,$attempt]);
    }

    public function take(Request $request, LearningClass $class, ClassPracticeAttempt $attempt, ClassPracticeAttemptService $service): View|RedirectResponse
    {
        $this->owned($request,$class,$attempt);
        $this->eligible($request,$class,$service);
        $attempt=$service->expire($attempt);
        if ($attempt->status!=='in_progress') return redirect()->route('member.class-practices.result',[$class,$attempt]);
        $attempt->load('practice');
        $items=$attempt->practice->items()->with(['version.options'])->get();
        $answers=$attempt->answers()->get()->keyBy('class_practice_item_id');
        return view('member.class-practices.take',compact('class','attempt','items','answers'));
    }

    public function save(Request $request, LearningClass $class, ClassPracticeAttempt $attempt, ClassPracticeAttemptService $service): JsonResponse
    {
        $this->owned($request,$class,$attempt);
        $this->eligible($request,$class,$service);
        $data=$request->validate([
            'item_id'=>['required','integer'],
            'answer'=>['present','nullable','string','in:A,B,C,D,E'],
            'flagged'=>['required','boolean'],
        ]);
        $result=$service->save($attempt,(int)$data['item_id'],$data['answer']??null,(bool)$data['flagged']);
        if ($result['closed']) return response()->json([
            'message'=>'Sesi latihan sudah selesai.','result_url'=>route('member.class-practices.result',[$class,$attempt]),
        ],409);
        return response()->json(['saved'=>true,'answered'=>$result['answered']]);
    }

    public function submit(Request $request, LearningClass $class, ClassPracticeAttempt $attempt, ClassPracticeAttemptService $service): RedirectResponse
    {
        $this->owned($request,$class,$attempt);
        $this->eligible($request,$class,$service);
        $service->submit($attempt);
        return redirect()->route('member.class-practices.result',[$class,$attempt])->with('success','Jawaban Latihan Kelas dikumpulkan.');
    }

    public function result(Request $request, LearningClass $class, ClassPracticeAttempt $attempt, ClassPracticeAttemptService $service): View|RedirectResponse
    {
        $this->owned($request,$class,$attempt);
        $this->eligible($request,$class,$service);
        $attempt=$service->expire($attempt);
        if ($attempt->status==='in_progress') return redirect()->route('member.class-practices.take',[$class,$attempt]);
        $attempt->load('practice');
        $items=$attempt->practice->items()->with(['version.options','version.question'])->get();
        $answers=$attempt->answers()->get()->keyBy('class_practice_item_id');
        return view('member.class-practices.result',compact('class','attempt','items','answers'));
    }
}
