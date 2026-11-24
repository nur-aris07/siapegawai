<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\QuickQuizVersion;
use App\Support\PilotLearningQaDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Private, SELECT-only cohort evaluation; no attempt settlement or personal export. */
final class PilotMeasurementService
{
    public const SCHEMA = 'PILOT-LEARNING-OPS-METRICS-1';
    public const HORIZON_HOURS = 24;
    public const STALE_MINUTES = 30;

    public static function utc(string $value): CarbonImmutable
    {
        if (!preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $value)) {
            throw new \InvalidArgumentException('UTC_SECOND_PRECISION_REQUIRED');
        }
        $c = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, 'UTC');
        if ($c === false || $c->format('Y-m-d\TH:i:s\Z') !== $value) {
            throw new \InvalidArgumentException('INVALID_UTC_DATE');
        }
        return $c;
    }

    public function input(string $cohort, int $edition, string $start, string $end, string $cutoff,
        string $population, int $maturityHours = self::HORIZON_HOURS, int $staleMinutes = self::STALE_MINUTES): array
    {
        if(!preg_match('/^[A-Z0-9_-]{1,40}$/D',$cohort)||$edition<1
            ||!in_array($population,['operational','research_opt_in'],true)
            ||$maturityHours<1||$maturityHours>168||$staleMinutes<1||$staleMinutes>180) {
            throw new \InvalidArgumentException('INVALID_REPORT_SCOPE');
        }
        $a=self::utc($start);$b=self::utc($end);$c=self::utc($cutoff);
        if(!$b->equalTo($a->addDays(7)) || $c->lt($a))throw new \InvalidArgumentException('INVALID_REPORT_WINDOW');
        return compact('cohort','edition','start','end','cutoff','population','maturityHours','staleMinutes');
    }

    /**
     * Independently testable pure metric arithmetic. Input contains only already
     * authorized attempt observations, never database objects or participant names.
     */
    public function aggregate(array $scope, int $eligiblePeople, array $attempts): array
    {
        $this->input($scope['cohort'],$scope['edition'],$scope['start'],$scope['end'],$scope['cutoff'],
            $scope['population'],$scope['maturityHours'],$scope['staleMinutes']);
        if($eligiblePeople<0||$eligiblePeople>8||count($attempts)>500)throw new \InvalidArgumentException('UNBOUNDED_POPULATION');
        $cutoff=self::utc($scope['cutoff']);$start=self::utc($scope['start']);$end=self::utc($scope['end']);
        $matureThreshold=$cutoff->subHours($scope['maturityHours']);
        $staleThreshold=$cutoff->subMinutes($scope['staleMinutes']);
        $seen=[];$counts=[];$mature=0;$completed=0;$abandoned=0;$active=0;$immature=0;
        foreach($attempts as $a){
            foreach(['id','alias','status','started_at','last_seen_at','updated_at'] as $required)
                if(!array_key_exists($required,$a))throw new \InvalidArgumentException('INVALID_ATTEMPT_SNAPSHOT');
            if(!is_int($a['id'])||$a['id']<1||!is_int($a['alias'])||$a['alias']<1
                ||!in_array($a['status'],['active','completed','abandoned'],true))throw new \InvalidArgumentException('UNKNOWN_ATTEMPT_STATUS');
            if(isset($seen[$a['id']]))throw new \RuntimeException('DUPLICATE_ATTEMPT_SNAPSHOT');
            $seen[$a['id']]=true;
            $started=self::utc($a['started_at']);$last=self::utc($a['last_seen_at']);$updated=self::utc($a['updated_at']);
            if($started->lt($start)||!$started->lt($end)||$started->gt($cutoff)
                ||$last->lt($started)||$last->gt($cutoff)||$updated->gt($cutoff))throw new \RuntimeException('INCONCLUSIVE_AS_OF');
            $counts[$a['alias']]=($counts[$a['alias']]??0)+1;
            if($started->gt($matureThreshold)){$immature++;continue;}
            $mature++;
            if($a['status']==='completed'){
                if(!is_string($a['finished_at']??null)||self::utc($a['finished_at'])->gt($cutoff))throw new \RuntimeException('INCONCLUSIVE_AS_OF');
                $completed++;
            }elseif($a['status']==='abandoned'){
                if(isset($a['finished_at']) && self::utc($a['finished_at'])->gt($cutoff))throw new \RuntimeException('INCONCLUSIVE_AS_OF');
                $abandoned++;
            }elseif($last->lt($staleThreshold)){$abandoned++;}else{$active++;}
        }
        if($mature!==$completed+$abandoned+$active)throw new \LogicException('PARTITION_INVARIANT_FAILED');
        $starters=count($counts);
        if($starters>$eligiblePeople)throw new \RuntimeException('STARTER_OUTSIDE_ELIGIBLE_PEOPLE');
        $repeat=count(array_filter($counts,fn($c)=>$c>1));
        return [
            'schema'=>self::SCHEMA,'population'=>$scope['population'],
            'cohort_provenance'=>hash('sha256',$scope['cohort'].'|'.$scope['edition'].'|'.$scope['start'].'|'.$scope['end']),
            'edition_version'=>$scope['edition'],'cutoff_utc'=>$scope['cutoff'],
            'maturity_hours'=>$scope['maturityHours'],'stale_minutes'=>$scope['staleMinutes'],
            'status'=>'DETERMINISTIC_SNAPSHOT_NOT_CAUSAL_EVIDENCE',
            'M01_eligible_people'=>$eligiblePeople,'M02_eligible_starts'=>count($attempts),
            'M03_mature_starts'=>$mature,'M04_completed'=>$completed,'M05_effectively_abandoned'=>$abandoned,
            'M06_remaining_mature_active'=>$active,'M07_completion_rate'=>$mature?round($completed/$mature,4):null,
            'M08_repeat_participants'=>$repeat,'M08_distinct_starters'=>$starters,
            'M08_repeat_rate'=>$starters?round($repeat/$starters,4):null,
            'immature_starts'=>$immature,'M09_support_ledger'=>'NOT_PROVIDED',
            'M10_independent_academic_review'=>'NOT_VERIFIED',
            'GP02'=>'OPEN', 'release'=>'PILOT_GO_HOLD',
        ];
    }

    /** Strict QA-only path; source transactions are SELECT-only on server. */
    public function fromDatabase(array $scope): array
    {
        PilotLearningQaDatabase::assertReadOnlyTarget();
        $this->input($scope['cohort'],$scope['edition'],$scope['start'],$scope['end'],$scope['cutoff'],
            $scope['population'],$scope['maturityHours'],$scope['staleMinutes']);
        if(!in_array((string)config('pilot.policy_version'),['QA-L1','QA-L2'],true)
            || !preg_match('/^QA-[A-Z0-9_-]{1,60}$/D',(string)config('pilot.research_version')))
            throw new \RuntimeException('SYNTHETIC_POLICY_CONTEXT_REQUIRED');
        $pdo=DB::connection()->getPdo();
        if(DB::connection()->transactionLevel()!==0)throw new \RuntimeException('READ_ONLY_REPORT_REQUIRES_NEW_TRANSACTION');
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        return DB::transaction(fn()=> $this->snapshot($scope),1);
    }

    private function snapshot(array $scope): array
    {
        $version=QuickQuizVersion::query()->with('quiz')->findOrFail($scope['edition']);
        if($version->status!=='published'||$version->quiz?->status!=='published'
            ||(int)$version->quiz->current_version_id!==(int)$version->id||$version->items()->count()!==30)
            throw new \RuntimeException('EDITION_SCOPE_NOT_VERIFIABLE');
        $cutoff=self::utc($scope['cutoff']);$start=self::utc($scope['start']);$end=self::utc($scope['end']);
        $grants=DB::table('access_grants')->where('grant_type','pilot_qq')->where('resource_type','quick_quiz_version')
            ->where('resource_id',$scope['edition'])->whereIn('status',['active','revoked'])
            ->where('starts_at',$start->format('Y-m-d H:i:s'))->where('ends_at',$end->format('Y-m-d H:i:s'))
            ->limit(10)->get();
        if($grants->count()>8)throw new \RuntimeException('COHORT_TOO_LARGE');
        if($grants->isEmpty())return $this->aggregate($scope,0,[]);
        $gids=$grants->pluck('id')->all();$uids=$grants->pluck('user_id')->unique()->all();
        if(count($uids)!==$grants->count())throw new \RuntimeException('DUPLICATE_PARTICIPANT_GRANT');
        $events=AuditLog::query()->where(function($q)use($gids,$uids){
            $q->where(fn($q)=>$q->where('subject_type','access_grant')->whereIn('subject_id',$gids))
              ->orWhere(fn($q)=>$q->where('subject_type','user')->whereIn('subject_id',$uids))
              ->orWhere(fn($q)=>$q->where('subject_type','quick_quiz_attempt')->whereIn('actor_id',$uids));
        })->whereIn('action',['pilot.granted','pilot.revoked','pilot.legal_accepted','pilot.research_opt_in',
            'pilot.research_declined','pilot.research_withdrawn','pilot.attempt_admitted'])
          ->orderBy('id')->limit(3000)->get();
        if($events->count()>=3000)throw new \RuntimeException('AUDIT_SNAPSHOT_TOO_LARGE');
        $consents=DB::table('consent_records')->whereIn('user_id',$uids)
            ->where('policy_version',config('pilot.policy_version'))->whereIn('source',['admin_invitation','pilot_reconsent'])
            ->where('agreed_at','<=',$cutoff->format('Y-m-d H:i:s'))->get();
        $consented=array_fill_keys($consents->pluck('user_id')->unique()->all(),true);
        $binding=app(PilotAccessService::class)->binding();$allowed=[];$grantByUser=[];
        foreach($grants as $g){
            $relevant=$events->filter(fn($e)=>$e->subject_type==='access_grant'&&(int)$e->subject_id===(int)$g->id);
            $issued=$relevant->filter(fn($e)=>$e->action==='pilot.granted'&&($e->after_summary['cohort']??null)===$scope['cohort']
                &&(int)($e->after_summary['version']??0)===$scope['edition']&&self::atOrBefore($e->created_at,$cutoff));
            if($issued->count()!==1)continue;
            $legal=$events->filter(fn($e)=>$e->subject_type==='user'&&(int)$e->subject_id===(int)$g->user_id
                &&$e->action==='pilot.legal_accepted'&&self::atOrBefore($e->created_at,$cutoff))->last();
            if(!isset($consented[$g->user_id])||($legal?->after_summary['binding']??null)!==$binding)continue;
            $revoke=$relevant->filter(fn($e)=>$e->action==='pilot.revoked');
            if($g->status==='revoked' && $revoke->count()!==1)throw new \RuntimeException('AMBIGUOUS_REVOKE_HISTORY');
            if($scope['population']==='research_opt_in'){
                $choice=$events->filter(fn($e)=>$e->subject_type==='user'&&(int)$e->subject_id===(int)$g->user_id
                    &&in_array($e->action,['pilot.research_opt_in','pilot.research_declined','pilot.research_withdrawn'],true)
                    &&self::atOrBefore($e->created_at,$cutoff))->last();
                if($choice?->action!=='pilot.research_opt_in'||($choice->after_summary['version']??null)!==config('pilot.research_version')
                    ||($choice->after_summary['digest']??null)!==config('pilot.research_sha256'))continue;
            }
            $allowed[(int)$g->user_id]=true;$grantByUser[(int)$g->user_id]=[$g,$revoke->first()];
        }
        if(!$allowed)return $this->aggregate($scope,0,[]);
        $rows=DB::table('quick_quiz_attempts')->whereIn('user_id',array_keys($allowed))
            ->where('quick_quiz_version_id',$scope['edition'])
            ->where('started_at','>=',$start->format('Y-m-d H:i:s'))->where('started_at','<',$end->format('Y-m-d H:i:s'))
            ->where('started_at','<=',$cutoff->format('Y-m-d H:i:s'))->orderBy('id')->limit(501)->get();
        if($rows->count()>500)throw new \RuntimeException('ATTEMPT_SCOPE_TOO_LARGE');
        $attempts=[];
        foreach($rows as $a){
            [$g,$revoke]=$grantByUser[(int)$a->user_id];
            $admissions=$events->filter(fn($e)=>$e->action==='pilot.attempt_admitted'&&$e->subject_type==='quick_quiz_attempt'
                &&(int)$e->subject_id===(int)$a->id&&(int)$e->actor_id===(int)$a->user_id
                &&(int)($e->after_summary['grant_id']??0)===(int)$g->id
                &&($e->after_summary['cohort']??null)===$scope['cohort']
                &&(int)($e->after_summary['version']??0)===$scope['edition']
                &&self::atOrBefore($e->created_at,$cutoff)
                &&(!$revoke||(int)$e->id<(int)$revoke->id));
            if($admissions->count()!==1)continue;
            $attempts[]=['id'=>(int)$a->id,'alias'=>(int)$a->user_id,'status'=>(string)$a->status,
                'started_at'=>self::asUtc($a->started_at),'last_seen_at'=>self::asUtc($a->last_seen_at),
                'updated_at'=>self::asUtc($a->updated_at),'finished_at'=>$a->finished_at?self::asUtc($a->finished_at):null];
        }
        return $this->aggregate($scope,count($allowed),$attempts);
    }

    private static function asUtc(string $v): string {return CarbonImmutable::parse($v,'UTC')->format('Y-m-d\TH:i:s\Z');}
    private static function atOrBefore($value,CarbonImmutable $cutoff): bool
    {return CarbonImmutable::parse($value,'UTC')->lte($cutoff);}
}
