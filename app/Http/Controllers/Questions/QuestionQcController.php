<?php
namespace App\Http\Controllers\Questions;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Services\QuestionQcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use App\Models\Topic;

final class QuestionQcController extends Controller
{
    public function index(Request $request, QuestionQcService $qc)
    {
        Gate::authorize('viewAny',Question::class);
        $filters=$request->validate([
            'code'=>['nullable','string','max:48','regex:/^[A-Za-z0-9-]*$/D'],
            'topic_id'=>['nullable','integer','min:1'],
            'qc_status'=>['nullable',Rule::in(['UNREVIEWED','NEEDS_REVISION','REVIEWED_INTERNAL','STALE'])],
        ]);
        $code=strtoupper(trim((string)($filters['code']??'')));
        $topicId=isset($filters['topic_id'])?(int)$filters['topic_id']:null;
        $status=$filters['qc_status']??'';
        $page=max(1,(int)$request->query('page',1));
        $query=Question::query()->with(['latestVersion.options','latestVersion.media','topic.subject'])
            ->when($code!=='',fn($q)=>$q->where('code','like','%'.$code.'%'))
            ->when($topicId,fn($q)=>$q->where('topic_id',$topicId));
        $rowFor=function(Question $q)use($qc): array {
            $version=$q->latestVersion;
            return ['question'=>$q,'version'=>$version,'qc'=>$version?$qc->snapshot($version):null];
        };
        if($status==='') {
            $questions=$query->orderByDesc('id')->paginate(30)->withQueryString();
            $rows=$questions->getCollection()->map($rowFor);
        } else {
            // Status is tied to a media-aware hash. It cannot safely be inferred
            // from audit JSON alone; scan in bounded chunks and paginate matches.
            $matches=0;$visible=[];
            $query->chunkById(100,function($chunk)use(&$matches,&$visible,$page,$status,$rowFor){
                foreach($chunk as $q){
                    $row=$rowFor($q);
                    if(($row['qc']['status']??'UNREVIEWED')!==$status)continue;
                    if($matches>=($page-1)*30 && count($visible)<30)$visible[]=$row;
                    $matches++;
                }
            });
            $rows=collect($visible);
            $questions=new LengthAwarePaginator($rows,$matches,30,$page,
                ['path'=>$request->url(),'query'=>$request->except('page')]);
        }
        $topics=Topic::query()->where('status','active')->orderBy('name')->get(['id','name']);
        return view('questions.admin.qc-index',compact('questions','rows','topics','code','topicId','status'));
    }

    public function show(Question $question,QuestionQcService $qc)
    {
        Gate::authorize('view',$question);
        $version=$question->versions()->with(['options','media'])->orderByDesc('version_number')->firstOrFail();
        $review=$qc->snapshot($version);
        return view('questions.admin.qc-show',compact('question','version','review'));
    }

    public function mark(Request $request,Question $question,QuestionQcService $qc): RedirectResponse
    {
        Gate::authorize('update',$question);
        $input=$request->validate(['hash'=>['required','regex:/^[a-f0-9]{64}$/D'],
            'decision'=>['required','in:REVIEWED_INTERNAL,NEEDS_REVISION'], 'notes'=>['nullable','string','max:500']]);
        $qc->mark($question,$request->user(),$input['hash'],$input['decision'],$input['notes']??null);
        return redirect()->route('questions.qc.show',$question)->with('success','Catatan QC internal telah tersimpan untuk hash versi ini. Bukan approval akademik atau hak media.');
    }
}
