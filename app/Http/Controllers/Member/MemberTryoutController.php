<?php
namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\TryoutAttempt;
use App\Models\TryoutPackage;
use App\Services\MemberTryoutService;
use App\Services\MemberSubjectAnalysisService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberTryoutController extends Controller
{
    private function owned(Request $request, TryoutAttempt $attempt): void
    {
        abort_unless((int)$attempt->user_id === (int)$request->user()->id,403);
    }

    public function index(Request $request): View
    {
        $packages=TryoutPackage::query()->where('status','published')->withCount('items')->orderBy('published_at','desc')->get();
        $attempts=TryoutAttempt::query()->where('user_id',$request->user()->id)
            ->with('package:id,code,name')->latest('id')->get()->groupBy('tryout_package_id');
        return view('member.tryouts.index',compact('packages','attempts'));
    }

    public function start(Request $request, TryoutPackage $package, MemberTryoutService $service): RedirectResponse
    {
        $attempt=$service->start($request->user(),$package);
        return redirect()->route('member.tryouts.take',$attempt);
    }

    public function take(Request $request, TryoutAttempt $attempt, MemberTryoutService $service): View|RedirectResponse
    {
        $this->owned($request,$attempt);
        $attempt=$service->expire($attempt);
        if ($attempt->status !== 'in_progress') return redirect()->route('member.tryouts.result',$attempt);
        $attempt->load('package');
        $items=$attempt->package->items()->with(['version.options','version.question.topic.subject'])->get();
        $answers=$attempt->answers()->get()->keyBy('tryout_package_item_id');
        return view('member.tryouts.take',compact('attempt','items','answers'));
    }

    public function save(Request $request, TryoutAttempt $attempt, MemberTryoutService $service): JsonResponse
    {
        $this->owned($request,$attempt);
        $data=$request->validate([
            'item_id'=>['required','integer'],
            'answer'=>['present','nullable','string','in:A,B,C,D,E'],
            'flagged'=>['required','boolean'],
        ]);
        $result=$service->save($attempt,(int)$data['item_id'],$data['answer']??null,(bool)$data['flagged']);
        if ($result['closed']) return response()->json(['message'=>'Waktu telah habis atau sesi sudah selesai.','result_url'=>route('member.tryouts.result',$attempt)],409);
        return response()->json(['saved'=>true,'answered'=>$result['answered']]);
    }

    public function submit(Request $request, TryoutAttempt $attempt, MemberTryoutService $service): RedirectResponse
    {
        $this->owned($request,$attempt);
        $service->submit($attempt);
        return redirect()->route('member.tryouts.result',$attempt)->with('success','Jawaban tryout telah dikumpulkan.');
    }

    public function result(Request $request, TryoutAttempt $attempt, MemberTryoutService $service, MemberSubjectAnalysisService $analysis): View|RedirectResponse
    {
        $this->owned($request,$attempt);
        $attempt=$service->expire($attempt);
        if ($attempt->status === 'in_progress') return redirect()->route('member.tryouts.take',$attempt);
        $attempt->load('package');
        $items=$attempt->package->items()->with(['version.options','version.question'])->get();
        $answers=$attempt->answers()->get()->keyBy('tryout_package_item_id');
        $subjectAnalysis = $analysis->forAttempt($attempt);
        return view('member.tryouts.result',compact('attempt','items','answers','subjectAnalysis'));
    }
}
