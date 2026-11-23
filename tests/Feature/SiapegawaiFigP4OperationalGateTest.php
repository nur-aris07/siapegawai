<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\Topic;
use App\Models\User;
use App\Services\QuestionEditorialMediaCoordinator;
use App\Services\QuestionEditorialService;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SiapegawaiFigP4OperationalGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
        Storage::fake('question_media_private');
    }

    public function test_public_figural_publish_does_not_have_a_default_enablement_flag(): void
    {
        $this->assertNotTrue(config('question_media.figural_publish_qa'));
        $this->assertFalse(config('filesystems.disks.question_media_private.serve'));
        $this->assertSame('private', config('filesystems.disks.question_media_private.visibility'));
        $this->assertArrayNotHasKey('question_media_private', config('filesystems.links'));
    }

    public function test_non_testing_environment_refuses_media_publication_even_with_qa_config_enabled(): void
    {
        $admin = User::factory()->create(['role'=>'superadmin', 'status'=>'active']);
        $q = Question::query()->create([
            'topic_id' => Topic::query()->firstOrFail()->id,
            'code' => 'FIG-P4-PUBLISH-GATE', 'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $v = QuestionVersion::query()->create([
            'question_id' => $q->id, 'version_number' => 1,
            'body' => 'Gambar yang digunakan untuk soal testing',
            'explanation' => 'Pembahasan soal figural testing harus lengkap',
            'review_status' => 'draft',
        ]);
        foreach (['A','B','C','D'] as $i=>$label) {
            $v->options()->create([
                'label'=>$label, 'body'=>'Pilihan '.$label,
                'sort_order'=>$i+1, 'is_correct'=>$label==='A',
            ]);
        }
        $bytes = file_get_contents(base_path('database/fixtures/fig-p3/pattern-stem.png'));
        $key = 'questions/bb/'.str_repeat('b',48).'.png';
        Storage::disk('question_media_private')->put($key, $bytes);
        $size = getimagesizefromstring($bytes);
        DB::table('question_version_media')->insert([
            'question_version_id'=>$v->id, 'slot'=>'stem', 'position'=>1,
            'object_key'=>$key, 'sha256'=>hash('sha256',$bytes),
            'mime_type'=>'image/png', 'byte_size'=>strlen($bytes),
            'width_px'=>$size[0], 'height_px'=>$size[1],
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $token = app(QuestionEditorialMediaCoordinator::class)->editToken($q);
        $wasEnvironment = app()->environment();
        $wasConfig = config('question_media.figural_publish_qa');
        try {
            config()->set('question_media.figural_publish_qa', true);
            app()->instance('env', 'production');
            try {
                app(QuestionEditorialService::class)->publish($q, $admin, $token);
                $this->fail('Figural publishing must fail closed outside isolated testing.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('publish', $exception->errors());
            }
        } finally {
            app()->instance('env', $wasEnvironment);
            config()->set('question_media.figural_publish_qa', $wasConfig);
        }
        $this->assertSame('draft', $v->fresh()->review_status);
        $this->assertNull($q->fresh()->current_version_id);
        $this->assertTrue(Storage::disk('question_media_private')->exists($key));
    }
}
