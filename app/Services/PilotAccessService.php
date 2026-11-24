<?php
namespace App\Services;

use App\Models\{AccessGrant, AuditLog, ConsentRecord, QuickQuiz, QuickQuizAttempt, QuickQuizVersion, User};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Single-cohort, synthetic-local profile. No production enablement in this slice. */
final class PilotAccessService
{
    public function enabled(): bool { return config('pilot.mode') === true; }
    public function version(): int { return (int)config('pilot.edition_version_id'); }
    private function local(): bool { return app()->environment('testing', 'local') && config('mail.default') === 'array'; }
    private function interval(): ?array
    {
        try {
            $a=config('pilot.window_start'); $b=config('pilot.window_end');
            if (!is_string($a)||!is_string($b)||!preg_match('/Z$/D',$a)||!preg_match('/Z$/D',$b)) return null;
            $start=CarbonImmutable::parse($a,'UTC'); $end=CarbonImmutable::parse($b,'UTC');
            return $start->lt($end) && $end->equalTo($start->addDays(7)) ? [$start,$end] : null;
        } catch (\Throwable) { return null; }
    }
    public function coherent(): bool
    {
        return $this->enabled() && $this->local() && config('siapegawai.registration_enabled')===false && config('siapegawai.google_auth_enabled')===false && config('siapegawai.account_email_enabled')===false && $this->version()>0 && $this->interval()!==null
            && is_string(config('pilot.cohort_code')) && preg_match('/^[A-Z0-9_-]{1,40}$/D',config('pilot.cohort_code'))
            && in_array(config('pilot.max_participants'),[6,7,8],true);
    }
    public function document(string $name): string
    {
        abort_unless(in_array($name,['privacy','terms','research'],true),404);
        return is_file(resource_path('pilot/'.$name.'.txt')) ? (string)file_get_contents(resource_path('pilot/'.$name.'.txt')) : '';
    }
    public function binding(): string
    {
        return hash('sha256',implode('|',[(string)config('pilot.policy_version'),(string)config('pilot.privacy_sha256'),(string)config('pilot.terms_sha256')]));
    }
    public function researchBinding(): string
    { return hash('sha256',(string)config('pilot.research_version').'|'.(string)config('pilot.research_sha256')); }
    public function validateResearchBinding(string $binding): void
    { if (!$this->legalReady()||!hash_equals($this->researchBinding(),$binding)) throw ValidationException::withMessages(['research'=>'Pemberitahuan riset berubah. Muat ulang halaman.']); }
    public function researchAllowed(User $u): bool
    {
        if (!$this->eligible($u)) return false;
        $e=AuditLog::query()->where('subject_type','user')->where('subject_id',$u->id)->whereIn('action',['pilot.research_opt_in','pilot.research_declined','pilot.research_withdrawn'])->latest('id')->first();
        return $e?->action==='pilot.research_opt_in' && ($e->after_summary['version']??null)===config('pilot.research_version') && ($e->after_summary['digest']??null)===config('pilot.research_sha256');
    }
    public function legalReady(): bool
    {
        if (!$this->coherent() || config('siapegawai.privacy_approved')!==true || config('siapegawai.terms_approved')!==true) return false;
        $v=config('pilot.policy_version'); $r=config('pilot.research_version'); $e=config('pilot.effective_at');
        // Synthetic versions explicitly distinguished from actual operator approval.
        if (!is_string($v)||!preg_match('/^QA-[A-Z0-9_-]{1,60}$/D',$v)||$v!==config('siapegawai.privacy_version')||!is_string($r)||!preg_match('/^QA-[A-Z0-9_-]{1,60}$/D',$r)||!is_string($e)) return false;
        try { if (CarbonImmutable::parse($e)->isFuture()) return false; } catch (\Throwable) { return false; }
        foreach (['privacy','terms','research'] as $doc) {
            $digest=config('pilot.'.$doc.'_sha256');
            if ($this->document($doc)===''||!is_string($digest)||!hash_equals($digest,hash('sha256',$this->document($doc)))) return false;
        }
        return true;
    }
    private function grants(User $u): Builder { return AccessGrant::query()->where('user_id',$u->id)->where('grant_type','pilot_qq'); }
    public function grant(User $u): ?AccessGrant
    {
        if (!$this->coherent()) return null;
        $rows=$this->grants($u)->limit(2)->get(); if ($rows->count()!==1) return null;
        $g=$rows->first(); [$a,$b]=$this->interval();
        if ($g->resource_type!=='quick_quiz_version'||(int)$g->resource_id!==$this->version()||!$g->starts_at?->equalTo($a)||!$g->ends_at?->equalTo($b)||!in_array($g->status,['active','revoked'],true)) return null;
        $events=AuditLog::query()->where('subject_type','access_grant')->where('subject_id',$g->id)->where('action','pilot.granted')->limit(2)->get();
        if ($events->count()!==1 || ($events->first()->after_summary['cohort']??null)!==config('pilot.cohort_code') || (int)($events->first()->after_summary['version']??0)!==$this->version()) return null;
        return $g;
    }
    public function inWindow(User $u): bool
    { $g=$this->grant($u); return $g && $g->status==='active' && now('UTC')->gte($g->starts_at) && now('UTC')->lt($g->ends_at); }
    public function currentConsent(User $u): bool
    {
        if (!$this->legalReady() || !ConsentRecord::query()->where('user_id',$u->id)->where('policy_version',config('pilot.policy_version'))->whereIn('source',['admin_invitation','pilot_reconsent'])->exists()) return false;
        $event=AuditLog::query()->where('subject_type','user')->where('subject_id',$u->id)->where('action','pilot.legal_accepted')->latest('id')->first();
        return ($event?->after_summary['binding']??null)===$this->binding();
    }
    public function eligible(User $u): bool { return $u->role==='participant' && $u->status==='active' && $this->grant($u)!==null && $this->currentConsent($u); }
    public function canStart(User $u): bool { return $this->eligible($u) && $this->inWindow($u) && config('pilot.starts_enabled')===true; }
    public function canReset(User $u): bool { return $u->isAdmin() || ($u->role==='participant' && ($u->status==='pending' ? $this->inWindow($u) : ($u->status==='active' && $this->grant($u)!==null))); }
    public function assertStart(User $u, QuickQuiz $q, ?int $expected): void
    { abort_unless($this->canStart($u),403); abort_unless($q->status==='published' && (int)$q->current_version_id===$this->version() && $expected===$this->version() && $q->currentVersion?->status==='published' && $q->currentVersion->items()->count()===30,404); }
    public function recordStart(User $u, QuickQuizAttempt $attempt): void
    {
        $g=$this->grant($u); abort_unless($g && $g->status==='active',403);
        $this->audit($u,'pilot.attempt_admitted','quick_quiz_attempt',$attempt->id,['grant_id'=>$g->id,'cohort'=>config('pilot.cohort_code'),'version'=>$this->version()]);
    }
    private function admittedEvents(User $u, AccessGrant $g): Builder
    {
        $q=AuditLog::query()->where('actor_id',$u->id)->where('subject_type','quick_quiz_attempt')->where('action','pilot.attempt_admitted')
            ->where('after_summary->grant_id',$g->id)->where('after_summary->cohort',config('pilot.cohort_code'))->where('after_summary->version',$this->version());
        if ($g->status==='revoked') {
            $events=AuditLog::query()->where('subject_type','access_grant')->where('subject_id',$g->id)->where('action','pilot.revoked')->limit(2)->get();
            if ($events->count()!==1) return $q->whereRaw('1=0');
            // Audit ID follows serialized user lock, including starts/revoke in same DB second.
            $q->where('id','<',$events->first()->id);
        }
        return $q;
    }
    public function admitted(User $u, QuickQuizAttempt $a): bool
    {
        if (!$this->eligible($u) || (int)$a->user_id!==(int)$u->id || (int)$a->quick_quiz_version_id!==$this->version()) return false;
        $g=$this->grant($u); if (!$a->started_at || $a->started_at->lt($g->starts_at)||$a->started_at->gte($g->ends_at)) return false;
        return $this->admittedEvents($u,$g)->where('subject_id',$a->id)->count()===1;
    }
    public function scopeAttempts(Builder $q, User $u): Builder
    {
        $g=$this->grant($u); if (!$this->eligible($u)||!$g) return $q->whereRaw('1=0');
        return $q->where('quick_quiz_version_id',$this->version())->where('started_at','>=',$g->starts_at)->where('started_at','<',$g->ends_at)
            ->whereIn('id',$this->admittedEvents($u,$g)->select('subject_id')->groupBy('subject_id')->havingRaw('COUNT(*) = 1'));
    }
    public function lockAdmission(): void
    {
        abort_unless($this->legalReady() && config('pilot.admission_enabled')===true,403);
        $v=QuickQuizVersion::query()->lockForUpdate()->findOrFail($this->version());
        abort_unless($v->status==='published' && $v->items()->count()===30 && QuickQuiz::query()->where('current_version_id',$v->id)->where('status','published')->exists(),403);
        abort_unless(AccessGrant::query()->where('grant_type','pilot_qq')->count()<config('pilot.max_participants'),409);
        [, $end]=$this->interval(); abort_unless(now('UTC')->lt($end),403);
    }
    public function issue(User $u,User $actor): void
    {
        [$a,$b]=$this->interval();
        $g=AccessGrant::query()->create(['user_id'=>$u->id,'grant_type'=>'pilot_qq','resource_type'=>'quick_quiz_version','resource_id'=>$this->version(),'starts_at'=>$a,'ends_at'=>$b,'status'=>'active','granted_by'=>$actor->id]);
        $this->audit($actor,'pilot.granted','access_grant',$g->id,['cohort'=>config('pilot.cohort_code'),'version'=>$this->version()]);
    }
    public function revoke(User $u,User $actor,string $reason): void
    {
        DB::transaction(function()use($u,$actor,$reason){
            $u=User::query()->lockForUpdate()->findOrFail($u->id); $g=$this->grant($u); abort_unless($g&&$g->status==='active',403);
            // Explicit self-assignment avoids MariaDB ON UPDATE timestamp mutation.
            DB::table('access_grants')->where('id',$g->id)->update(['status'=>'revoked','revoke_reason'=>$reason,'starts_at'=>DB::raw('starts_at'),'updated_at'=>now('UTC')]);
            $this->audit($actor,'pilot.revoked','access_grant',$g->id,['cohort'=>config('pilot.cohort_code'),'version'=>$this->version()]);
        },3);
    }
    public function validateConsent(string $binding): void
    { if (!$this->legalReady() || !hash_equals($this->binding(),$binding)) throw ValidationException::withMessages(['consent'=>'Kebijakan tidak tersedia atau berubah. Muat ulang halaman.']); }
    public function research(User $u,bool $yes,bool $initial=false): void
    { $this->audit($u,$yes?'pilot.research_opt_in':($initial?'pilot.research_declined':'pilot.research_withdrawn'),'user',$u->id,['version'=>config('pilot.research_version'),'digest'=>config('pilot.research_sha256'),'cohort'=>config('pilot.cohort_code')]); }
    public function audit(User $actor,string $action,string $type,int $id,array $data): void
    { AuditLog::query()->create(['actor_id'=>$actor->id,'action'=>$action,'subject_type'=>$type,'subject_id'=>$id,'after_summary'=>$data,'created_at'=>now('UTC')]); }
}
