<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialMedia;
use App\Models\User;
use App\Support\FigP4OperationalEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SiapegawaiFigP4OpsClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_crosswalk_has_exact_fig_cases_and_never_claims_external_approval(): void
    {
        $good = array_fill_keys([
            'full_regression', 'legacy_figural_focused', 'chrome_browser_smoke',
            'fig_p4_pdf_focused', 'fig_p4_fairness_synthetic',
        ], 'PASS');
        $actual = FigP4OperationalEvidence::reconcile($good);
        $this->assertSame(FigP4OperationalEvidence::CASES, array_keys($actual['cases']));
        $this->assertSame('PASS_SYNTHETIC', $actual['cases']['AC-FIG-09']);
        $this->assertSame('OPEN_REAL_DEVICE_AND_POLICY', $actual['cases']['AC-FIG-11']);
        $this->assertSame('BLOCKED_NO_INDEPENDENT_TARGET', $actual['cases']['AC-FIG-12']);
        $this->assertSame('PASS_SYNTHETIC_OBSERVABILITY_ONLY', $actual['p4_e04_v03']);
        $this->assertFalse($actual['pilot_go']);
        $this->assertFalse($actual['public_seo_sales_go']);
        $bad = FigP4OperationalEvidence::reconcile([]);
        $this->assertSame('NOT_PROVEN', $bad['cases']['AC-FIG-09']);
        $this->assertSame('INCONCLUSIVE_OBSERVABILITY', $bad['p4_e04_v03']);
    }

    public function test_linked_private_class_pdf_serves_exact_bytes_and_get_never_mutates_domain(): void
    {
        config(['pilot.mode' => false]);
        Storage::fake('class_media_private');
        $admin = User::factory()->create(['role'=>'superadmin', 'status'=>'active']);
        $participant = User::factory()->create(['role'=>'participant', 'status'=>'active']);
        $foreigner = User::factory()->create(['role'=>'participant', 'status'=>'active']);
        $class = LearningClass::query()->create([
            'slug'=>'fig-p4-ops-pdf-'.bin2hex(random_bytes(4)),
            'title'=>'Figural recovery PDF QA', 'description'=>'Synthetic only',
            'status'=>'open', 'capacity'=>5, 'created_by'=>$admin->id,
        ]);
        $material = LearningMaterial::query()->create([
            'class_id'=>$class->id, 'title'=>'Synthetic PDF', 'content'=>'QA only',
            'status'=>'published', 'sort_order'=>1, 'published_at'=>now(),
        ]);
        $pdf = file_get_contents(base_path('database/fixtures/fig-p4/qa-class-restore.pdf'));
        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $object = 'classes/'.$class->id.'/materials/'.$material->id.'/'.(string) \Illuminate\Support\Str::uuid().'.pdf';
        $disk = Storage::disk('class_media_private');
        $this->assertTrue($disk->put($object, $pdf));
        $media = LearningMaterialMedia::query()->create([
            'material_id'=>$material->id, 'kind'=>'pdf', 'title'=>'QA PDF',
            'private_path'=>$object, 'original_name'=>'qa.pdf',
            'size_bytes'=>strlen($pdf), 'sha256'=>hash('sha256',$pdf),
        ]);
        ClassEnrollment::query()->create([
            'class_id'=>$class->id, 'user_id'=>$participant->id, 'status'=>'active',
            'approved_by'=>$admin->id, 'approved_at'=>now(),
        ]);
        $route = route('member.classes.material.media.pdf', [$class,$material,$media]);
        $writes=[];
        DB::listen(static function($query) use (&$writes): void {
            if (preg_match('/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $query->sql)) {
                $writes[]=$query->sql;
            }
        });
        $this->actingAs($participant)->get($route)->assertOk()
            ->assertHeader('Content-Type','application/pdf')
            ->assertHeader('X-Content-Type-Options','nosniff');
        $this->assertSame(hash('sha256',$pdf), hash('sha256',(string)$disk->get($object)));
        $this->actingAs($foreigner)->get($route)->assertNotFound();
        $this->actingAs($participant)->get(route('member.classes.material.media.pdf', [$class,$material,99999999]))->assertNotFound();
        $disk->put($object, 'CORRUPTED-RESTORE-COPY');
        $this->actingAs($participant)->get($route)->assertNotFound();
        $this->assertSame('CORRUPTED-RESTORE-COPY', $disk->get($object));
        $disk->put($object, $pdf); // recovery only within a disposable testing disk
        $this->actingAs($participant)->get($route)->assertOk();
        $this->assertSame([], $writes, 'Private media GET, including denied/corrupt requests, must never issue domain writes.');
    }
}
