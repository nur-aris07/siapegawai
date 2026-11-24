<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuickQuiz;
use App\Models\Topic;
use App\Services\PilotContentQcService;
use App\Services\PilotMeasurementService;
use App\Services\QuickQuizPinEligibility;
use App\Support\PilotLearningQaDatabase;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiapegawaiPilotLearningOpsTest extends TestCase
{
    use RefreshDatabase;

    private function scope(string $population='operational'): array
    {
        return app(PilotMeasurementService::class)->input('QA-COHORT',1,'2026-11-01T00:00:00Z',
            '2026-11-08T00:00:00Z','2026-11-05T12:00:00Z',$population);
    }

    private function observation(int $id,int $alias,string $status,string $started,string $last,?string $finished=null): array
    {
        return ['id'=>$id,'alias'=>$alias,'status'=>$status,'started_at'=>$started,
            'last_seen_at'=>$last,'finished_at'=>$finished,'updated_at'=>'2026-11-05T11:40:00Z'];
    }

    public function test_qc_template_exact_30_is_unsigned_and_share_summary_does_not_leak_keys(): void
    {
        $q=app(PilotContentQcService::class);
        $rows=$q->template();$this->assertCount(30,$rows);
        $this->assertSame(range(1,30),array_column($rows,'planned_ordinal'));
        $this->assertSame(['pattern_sequence'=>8,'visual_analogy'=>6,'classification'=>6,'matrix_relationship'=>5,'text_reasoning'=>5],
            array_count_values(array_column($rows,'competency')));
        $this->assertSame(['PENDING_INDEPENDENT_REVIEW'],array_unique(array_column($rows,'academic_result')));
        $this->assertSame(['UNVERIFIED'],array_unique(array_column($rows,'rights_result')));
        $this->assertSame(['NOT_GRANTED'],array_unique(array_column($rows,'final_signoff')));
        $csv=$q->csv($rows);$this->assertCount(31,explode("\n",trim($csv)));
        $this->assertStringContainsString('answer_key_private',$csv);
        $summary=json_encode($q->safeSummary($rows),JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('answer_key_private',$summary);
        $this->assertStringContainsString('OPEN_REVIEW_REQUIRED',$summary);
    }

    public function test_exact_version_private_inspection_rejects_other_database_and_template_remains_available(): void
    {
        $this->assertCount(30,app(PilotContentQcService::class)->template());
        config(['pilot.qa_test_database'=>'unexpected_database']);
        $this->expectException(\RuntimeException::class);
        app(PilotContentQcService::class)->forEdition(1,app(QuickQuizPinEligibility::class));
    }

    public function test_aggregate_reference_vector_and_zero_denominator(): void
    {
        $service=app(PilotMeasurementService::class);
        $scope=$this->scope();
        $rows=[
            $this->observation(11,1,'completed','2026-11-02T00:00:00Z','2026-11-02T00:12:00Z','2026-11-02T00:15:00Z'),
            $this->observation(12,1,'abandoned','2026-11-02T09:00:00Z','2026-11-02T09:05:00Z'),
            $this->observation(13,1,'active','2026-11-03T01:00:00Z','2026-11-05T11:30:00Z'), // strict grace boundary
            $this->observation(14,2,'completed','2026-11-03T08:00:00Z','2026-11-03T08:14:00Z','2026-11-03T08:15:00Z'),
            $this->observation(15,2,'active','2026-11-04T02:00:00Z','2026-11-05T11:29:59Z'), // stale
            $this->observation(16,3,'active','2026-11-05T10:00:00Z','2026-11-05T11:30:00Z'), // immature
        ];
        $r=$service->aggregate($scope,3,$rows);
        foreach(['M01_eligible_people'=>3,'M02_eligible_starts'=>6,'M03_mature_starts'=>5,
            'M04_completed'=>2,'M05_effectively_abandoned'=>2,'M06_remaining_mature_active'=>1,
            'M08_repeat_participants'=>2,'immature_starts'=>1] as $metric=>$expected){$this->assertSame($expected,$r[$metric],$metric);}
        $this->assertSame(0.4,$r['M07_completion_rate']);
        $this->assertSame(3,$r['M08_distinct_starters']);
    }

    public function test_aggregate_report_zero_starters_or_eligible_people_is_null_not_zero_percent(): void
    {
        $r=app(PilotMeasurementService::class)->aggregate($this->scope(),0,[]);
        $this->assertSame(0,$r['M03_mature_starts']);
        $this->assertNull($r['M07_completion_rate']);
        $this->assertNull($r['M08_repeat_rate']);
    }

    public function test_late_mutation_is_inconclusive_not_retroactively_counted(): void
    {
        $a=$this->observation(1,1,'completed','2026-11-02T00:00:00Z','2026-11-02T00:04:00Z','2026-11-02T00:05:00Z');
        $a['updated_at']='2026-11-06T00:00:00Z';
        $this->expectExceptionMessage('INCONCLUSIVE_AS_OF');
        app(PilotMeasurementService::class)->aggregate($this->scope(),1,[$a]);
    }

    public function test_duplicate_and_invalid_times_fail_closed(): void
    {
        $a=$this->observation(1,1,'active','2026-11-02T00:00:00Z','2026-11-02T00:00:01Z');
        try{app(PilotMeasurementService::class)->aggregate($this->scope(),1,[$a,$a]);$this->fail('duplicate should stop');}
        catch(\RuntimeException $e){$this->assertSame('DUPLICATE_ATTEMPT_SNAPSHOT',$e->getMessage());}
        $this->expectException(\InvalidArgumentException::class);
        app(PilotMeasurementService::class)->input('QA-COHORT',1,'2026-11-01T00:00:00+00:00',
            '2026-11-08T00:00:00Z','2026-11-05T12:00:00Z','operational');
    }

    public function test_research_population_is_separate_and_unknown_status_is_rejected(): void
    {
        $s=$this->scope('research_opt_in');
        $this->assertSame('research_opt_in',app(PilotMeasurementService::class)->aggregate($s,0,[])['population']);
        $a=$this->observation(3,1,'strange','2026-11-02T00:00:00Z','2026-11-02T00:00:01Z');
        $this->expectException(\InvalidArgumentException::class);
        app(PilotMeasurementService::class)->aggregate($this->scope(),1,[$a]);
    }

    public function test_private_faq_is_not_a_public_route_and_uses_owner_approved_contact(): void
    {
        $this->get('/pilot/bantuan')->assertNotFound();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $html=view('pilot.faq')->render();
        $this->assertStringContainsString('siapegawai@gmail.com',$html);
        $this->assertStringContainsString('mailto:siapegawai@gmail.com',$html);
        $this->assertStringContainsString('Tidak ada janji waktu respons',$html);
        $this->assertStringContainsString('aria-labelledby',$html);
        $this->assertStringNotContainsString('<form',$html);
    }
}
