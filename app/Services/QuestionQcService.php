<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Internal review ledger bound to the exact immutable content digest, never GP02 certification. */
final class QuestionQcService
{
    private const ACTION = 'question.qc_internal_review';
    public function __construct(private readonly QuestionVersionCanonicalizer $canonical) {}

    public function snapshot(QuestionVersion $version): array
    {
        $hash=$this->canonical->hashWithMedia($version);
        $event=AuditLog::query()->where('subject_type',QuestionVersion::class)->where('subject_id',$version->id)
            ->where('action',self::ACTION)->orderByDesc('id')->first();
        $details=$event?->after_summary ?? [];
        $status='UNREVIEWED';
        if($event){
            $status=hash_equals((string)($details['hash']??''),$hash)?(string)($details['decision']??'UNREVIEWED'):'STALE';
        }
        return ['hash'=>$hash,'status'=>$status,'event'=>$event,'reviewed_by'=>$event?->actor_id,
            'reviewed_at'=>$event?->created_at,'notes'=>(string)($details['notes']??'')];
    }

    public function mark(Question $question, User $actor, string $digest, string $decision, ?string $notes): array
    {
        Gate::authorize('update',$question);
        if(!in_array($decision,['REVIEWED_INTERNAL','NEEDS_REVISION'],true))
            throw ValidationException::withMessages(['decision'=>'Keputusan QC tidak tersedia.']);
        $notes=trim((string)$notes);
        if(mb_strlen($notes)>500)throw ValidationException::withMessages(['notes'=>'Catatan maksimal 500 karakter.']);
        if(!preg_match('/^[a-f0-9]{64}$/D',$digest))throw ValidationException::withMessages(['hash'=>'Digest tidak valid.']);
        return DB::transaction(function()use($question,$actor,$digest,$decision,$notes){
            $locked=Question::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
            $version=$locked->versions()->orderByDesc('version_number')->lockForUpdate()->firstOrFail();
            $actual=$this->canonical->hashWithMedia($version);
            if(!hash_equals($actual,$digest))throw ValidationException::withMessages(['hash'=>'Versi soal berubah sejak dibuka; muat ulang sebelum QC.']);
            $before=$this->snapshot($version);
            $event=AuditLog::query()->create([
                'actor_id'=>$actor->id,'action'=>self::ACTION,
                'subject_type'=>QuestionVersion::class,'subject_id'=>$version->id,
                'before_summary'=>['prior_status'=>$before['status'],'prior_hash'=>$before['hash']],
                'after_summary'=>['hash'=>$actual,'decision'=>$decision,'notes'=>$notes,
                    'scope'=>'INTERNAL_ONLY_NO_GP02_OR_RIGHTS_APPROVAL','version_number'=>$version->version_number],
                'created_at'=>now(),
            ]);
            return ['event_id'=>$event->id,'status'=>$decision,'version_id'=>$version->id,'hash'=>$actual];
        },1);
    }
}
