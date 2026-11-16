<?php
namespace App\Services;

use App\Models\ClassPractice;
use App\Models\Question;
use App\Models\QuickQuiz;
use App\Models\TryoutPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Append-only batch orchestration. Editorial services remain the sole writers of pinned items and audit. */
class BulkQuestionAssignmentService
{
    public function __construct(
        private TryoutEditorialService $cat,
        private QuickQuizEditorialService $qq,
        private ClassPracticeEditorialService $classPractice,
    ) {}

    private function context(string $domain, int $id): array
    {
        if ($domain === 'cat') {
            $target = TryoutPackage::query()->findOrFail($id);
            Gate::authorize('update', $target);
            if ($target->status !== 'draft') $this->invalid('Hanya draft CAT yang dapat menerima penugasan.');
            $meta = ['code'=>$target->code,'name'=>$target->name,'description'=>$target->description,
                'duration_minutes'=>$target->duration_minutes,'max_attempts'=>$target->max_attempts];
            $items = $target->items()->orderBy('sort_order')->orderBy('id')->pluck('question_version_id')->map(fn($v)=>(int)$v)->all();
            return [$target, $target, $meta, $items, 100];
        }
        if ($domain === 'qq') {
            $target=QuickQuiz::query()->findOrFail($id);
            Gate::authorize('update',$target);
            if ($target->status === 'archived') $this->invalid('Quiz sudah diarsipkan.');
            $draft=$target->draftVersion();
            if (!$draft) $this->invalid('Buat draft revisi Quiz Kilat melalui editor terlebih dahulu.');
            $meta=['status'=>$target->status,'draft_id'=>$draft->id,'title'=>$draft->title,
                'description'=>$draft->description,'seconds_per_question'=>$draft->seconds_per_question];
            $items=$draft->items()->orderBy('sort_order')->orderBy('id')->pluck('question_version_id')->map(fn($v)=>(int)$v)->all();
            return [$target,$draft,$meta,$items,40];
        }
        if ($domain === 'kelas') {
            $target=ClassPractice::query()->findOrFail($id);
            Gate::authorize('update',$target);
            if ($target->status !== 'draft') $this->invalid('Hanya draft latihan Kelas yang dapat menerima penugasan.');
            $meta=['class_id'=>$target->class_id,'title'=>$target->title,'description'=>$target->description,
                'material_id'=>$target->material_id,'duration_minutes'=>$target->duration_minutes,
                'max_attempts'=>$target->max_attempts,'sort_order'=>$target->sort_order];
            $items=$target->items()->orderBy('sort_order')->orderBy('id')->pluck('question_version_id')->map(fn($v)=>(int)$v)->all();
            return [$target,$target,$meta,$items,100];
        }
        abort(404);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['selection'=>$message]);
    }

    private function validate(string $domain, $draft, array $ids): void
    {
        if ($domain==='cat') $this->cat->validateBulkSelection($draft,$ids);
        elseif ($domain==='qq') $this->qq->validateBulkSelection($draft,$ids);
        else $this->classPractice->validateBulkSelection($draft,$ids);
    }

    private function key(): string
    {
        $key=(string)config('app.key');
        if ($key === '') throw new RuntimeException('APP_KEY is required for bulk assignment preview.');
        return $key;
    }

    private function seal(array $payload): string
    {
        $json=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $raw=rtrim(strtr(base64_encode($json),'+/','-_'),'=');
        return $raw.'.'.hash_hmac('sha256',$raw,$this->key());
    }

    private function unseal(string $token): array
    {
        $pair=explode('.',$token,2);
        if (count($pair)!==2 || strlen($token)>12000 || !hash_equals(hash_hmac('sha256',$pair[0],$this->key()),$pair[1])) {
            $this->invalid('Pratinjau tidak sah. Lakukan pratinjau ulang.');
        }
        $payload=json_decode(base64_decode(strtr($pair[0],'-_','+/'),true) ?: '',true);
        if (!is_array($payload) || !isset($payload['issued']) || time()-(int)$payload['issued']>900 || (int)$payload['issued']>time()+30) {
            $this->invalid('Pratinjau sudah kedaluwarsa. Lakukan pratinjau ulang.');
        }
        return $payload;
    }

    /** Lists current published versions; page changes never mutate client-side selection. */
    public function search(string $domain, int $target, string $term, string $subject, int $page): array
    {
        $this->context($domain,$target);
        $query=Question::query()->with(['topic.subject','currentVersion.options'])
            ->where('status','published')->whereNotNull('current_version_id')
            ->whereHas('currentVersion',fn($q)=>$q->where('review_status','published')->whereNotNull('published_at'))
            ->whereHas('topic',fn($q)=>$q->where('status','active')->whereHas('subject',fn($s)=>$s->where('status','active')));
        if ($term!=='') {
            $like='%'.$term.'%';
            $query->where(fn($q)=>$q->where('code','like',$like)->orWhereHas('currentVersion',fn($v)=>$v->where('body','like',$like)));
        }
        if ($subject!=='') $query->whereHas('topic.subject',fn($q)=>$q->where('code',$subject));
        $rows=$query->orderBy('code')->simplePaginate(15,['*'],'page',$page);
        return ['data'=>$rows->getCollection()->map(fn(Question $q)=>[
            'id'=>(int)$q->current_version_id, 'code'=>$q->code,
            'subject'=>$q->topic?->subject?->code,
            'version'=>$q->currentVersion?->version_number,
            'summary'=>Str::limit((string)$q->currentVersion?->body,100),
        ])->all(),'page'=>$page,'has_more'=>$rows->hasMorePages()];
    }

    public function preview(string $domain,int $target, User $actor,array $versions): array
    {
        // Preview uses a read-only database transaction for consistent eligibility checks.
        return DB::transaction(function() use($domain,$target,$actor,$versions) {
            [$model,$draft,$meta,$base,$max]=$this->context($domain,$target);
            $errors=[];$valid=[];$seen=[];
            if (count($versions)>100) $this->invalid('Maksimal 100 pilihan per operasi.');
            $candidate=array_map('intval',$versions);
            // Common path is one domain-validation pass, not O(n^2) revalidation.
            if (count(array_unique($candidate))===count($candidate) &&
                !array_intersect($candidate,$base) && count($base)+count($candidate)<=$max) {
                try { $this->validate($domain,$draft,array_merge($base,$candidate)); $valid=$candidate; }
                catch (ValidationException $e) { /* Per-item recovery below for actionable errors. */ }
            }
            if (!$valid) {
                foreach ($candidate as $i=>$id) {
                    if ($id<=0 || isset($seen[$id])) { $errors[]=['index'=>$i+1,'id'=>$id,'message'=>'ID tidak valid atau versi sama dipilih dua kali.']; continue; }
                    $seen[$id]=true;
                    if (in_array($id,$base,true)) { $errors[]=['index'=>$i+1,'id'=>$id,'message'=>'Versi sudah berada di draft.']; continue; }
                    if (count($base)+count($valid)+1>$max) { $errors[]=['index'=>$i+1,'id'=>$id,'message'=>'Melebihi batas '.$max.' soal untuk target ini.']; continue; }
                    try { $this->validate($domain,$draft,array_merge($base,$valid,[$id])); $valid[]=$id; }
                    catch (ValidationException $e) { $errors[]=['index'=>$i+1,'id'=>$id,'message'=>implode(' ',array_merge(...array_values($e->errors())))]; }
                }
            }
            $token=null;
            if (!$errors && $valid) {
                $token=$this->seal(['domain'=>$domain,'target'=>$target,'actor'=>$actor->id,'meta'=>$meta,
                    'base'=>$base,'append'=>$valid,'issued'=>time()]);
            }
            return ['existing_count'=>count($base),'valid_count'=>count($valid),'invalid_count'=>count($errors),
                'total_after'=>count($base)+count($valid),'max'=>$max,'append_order'=>$valid,
                'errors'=>$errors,'token'=>$token,'can_confirm'=>$token!==null];
        },3);
    }

    public function confirm(string $domain,int $target,User $actor,string $token): array
    {
        // Authorize the target even when a caller supplies an invalid/expired token.
        $this->context($domain,$target);
        $payload=$this->unseal($token);
        if (($payload['domain']??null)!==$domain || (int)($payload['target']??0)!==$target ||
            (int)($payload['actor']??0)!==$actor->id || !is_array($payload['base']??null) ||
            !is_array($payload['append']??null) || !is_array($payload['meta']??null) || !$payload['append']) {
            $this->invalid('Pratinjau tidak sesuai dengan target atau akun.');
        }
        return DB::transaction(function() use($domain,$target,$actor,$payload) {
            $type=['cat'=>TryoutPackage::class,'qq'=>QuickQuiz::class,'kelas'=>ClassPractice::class][$domain]??null;
            if (!$type) abort(404);
            // All existing editorial updates lock the same parent record first.
            $type::query()->whereKey($target)->lockForUpdate()->firstOrFail();
            [$model,$draft,$meta,$base,$max]=$this->context($domain,$target);
            $previous=array_map('intval',$payload['base']);
            $append=array_map('intval',$payload['append']);
            $expected=array_merge($previous,$append);
            if ($base===$expected && $meta===$payload['meta']) {
                return ['status'=>'already_applied','count'=>count($append),'total'=>count($base)];
            }
            if ($base!==$previous || $meta!==$payload['meta']) {
                abort(409, 'Draft berubah sejak pratinjau. Periksa daftar dan lakukan pratinjau ulang; tidak ada perubahan tersimpan.');
            }
            if (count($expected)>$max || count($append)>100) $this->invalid('Jumlah soal melewati batas.');
            // Same domain-specific validation as preview, repeated under the parent lock.
            $this->validate($domain,$draft,$expected);
            if ($domain==='cat') {
                $this->cat->update($model, array_merge($meta,['version_ids'=>$expected]),$actor);
            } elseif ($domain==='qq') {
                $this->qq->update($model,[
                    'title'=>$meta['title'],'description'=>$meta['description'],
                    'seconds_per_question'=>$meta['seconds_per_question'],'version_ids'=>$expected,
                ],$actor);
            } else {
                $this->classPractice->update($model,array_merge($meta,['version_ids'=>$expected]),$actor);
            }
            return ['status'=>'committed','count'=>count($append),'total'=>count($expected)];
        },3);
    }
}
