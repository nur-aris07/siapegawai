<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SiapegawaiFigP4ClassPdfRestoreFixtureTest extends TestCase
{
    use RefreshDatabase;

    private function createQaClass(): array
    {
        Storage::fake('class_media_private');
        $admin = User::factory()->create([
            'email' => 'figp3-qa-admin@example.test', 'role' => 'superadmin', 'status' => 'active',
        ]);
        $participant = User::factory()->create([
            'email' => 'figp3-qa-participant@example.test', 'role' => 'participant', 'status' => 'active',
        ]);
        $class = LearningClass::query()->create([
            'slug' => 'figp3-qa-kelas-01', 'title' => 'Kelas FIG-P4 V-02 QA',
            'description' => 'Hanya untuk pengujian pemulihan data dan media sintetis.',
            'status' => 'open', 'capacity' => 5, 'created_by' => $admin->id,
        ]);
        ClassEnrollment::query()->create([
            'class_id' => $class->id, 'user_id' => $participant->id, 'status' => 'active',
            'approved_by' => $admin->id, 'approved_at' => now(),
        ]);
        return [$admin, $participant, $class];
    }

    private function execute(string $mode): int
    {
        return Artisan::call('siapegawai:fig-p4-class-pdf', ['--'.$mode => true]);
    }

    public function test_dry_run_is_select_only_and_requires_real_p3_qa_class(): void
    {
        Storage::fake('class_media_private');
        $this->assertSame(2, $this->execute('dry-run'));
        // Artisan::output() consumes the buffered output; read it exactly once
        // per invocation when checking multiple diagnostic guarantees.
        $diagnostic = Artisan::output();
        $this->assertStringContainsString('BLOCKED_MISSING_FIGP3_FIXTURE', $diagnostic);
        $this->assertStringContainsString('fig-p3-demo --env=testing --apply --bootstrap-users --qa-quota', $diagnostic);
        $this->assertSame(2, $this->execute('apply'));
        $this->assertSame(2, $this->execute('verify'));
        $this->assertDatabaseCount('learning_materials', 0);
        $this->assertDatabaseCount('learning_material_media', 0);
        $this->createQaClass();
        $writes = [];
        DB::listen(static function ($query) use (&$writes): void {
            if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $this->assertSame(0, $this->execute('dry-run'));
        $this->assertStringContainsString('READY', Artisan::output());
        $this->assertSame([], $writes);
        $this->assertDatabaseCount('learning_material_media', 0);
    }

    public function test_apply_links_one_valid_private_pdf_with_published_version_and_is_idempotent(): void
    {
        [$admin, $participant, $class] = $this->createQaClass();
        $this->assertSame(0, $this->execute('apply'));
        $this->assertSame(0, $this->execute('verify'));
        $this->assertSame(0, $this->execute('apply'));
        $this->assertDatabaseCount('learning_materials', 1);
        $this->assertDatabaseCount('learning_material_media', 1);
        $this->assertDatabaseCount('learning_material_versions', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'class.media_added']);
        $material = LearningMaterial::query()->sole();
        $media = LearningMaterialMedia::query()->sole();
        $this->assertSame('published', $material->status);
        $this->assertSame('pdf', $media->kind);
        $this->assertSame(hash_file('sha256', base_path('database/fixtures/fig-p4/qa-class-restore.pdf')), $media->sha256);
        Storage::disk('class_media_private')->assertExists($media->private_path);
        $download = route('member.classes.material.media.pdf', [$class, $material, $media]);
        $this->actingAs($participant)->get($download)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs(User::factory()->create(['role'=>'participant','status'=>'active']))
            ->get($download)->assertNotFound();
        ClassEnrollment::query()->where('class_id', $class->id)
            ->where('user_id', $participant->id)->update(['status' => 'revoked']);
        $this->actingAs($participant)->get($download)->assertNotFound();
    }

    public function test_empty_private_class_root_is_created_only_on_explicit_apply(): void
    {
        $this->createQaClass();
        $disk = Storage::disk('class_media_private');
        $root = rtrim($disk->path(''), '/\\');
        $this->assertTrue(\Illuminate\Support\Facades\File::deleteDirectory($root));
        $this->assertDirectoryDoesNotExist($root);
        $this->assertSame(0, $this->execute('dry-run'));
        $this->assertDirectoryDoesNotExist($root);
        $this->assertSame(0, $this->execute('apply'));
        $this->assertDirectoryExists($root);
        $this->assertSame(0, $this->execute('verify'));
        $this->assertDatabaseCount('learning_material_media', 1);
    }

    public function test_existing_qa_user_ids_are_supported_without_bootstrap_emails(): void
    {
        [$admin, $participant] = $this->createQaClass();
        $admin->update(['email' => 'fig-p4-existing-admin@example.test']);
        $participant->update(['email' => 'fig-p4-existing-member@example.test']);
        $this->assertSame(0, $this->execute('apply'));
        $this->assertSame(0, $this->execute('verify'));
        $this->assertDatabaseCount('learning_material_media', 1);
    }

    public function test_existing_pdf_corruption_fails_closed_and_never_overwrites(): void
    {
        $this->createQaClass();
        $this->assertSame(0, $this->execute('apply'));
        $media = LearningMaterialMedia::query()->sole();
        Storage::disk('class_media_private')->put($media->private_path, 'corrupt');
        $this->assertSame(2, $this->execute('verify'));
        $this->assertSame(2, $this->execute('apply'));
        $this->assertSame('corrupt', Storage::disk('class_media_private')->get($media->private_path));
        $this->assertDatabaseCount('learning_material_media', 1);
    }

    public function test_revoked_enrollment_blocks_fixture_creation(): void
    {
        [, $participant, $class] = $this->createQaClass();
        ClassEnrollment::query()->where('class_id', $class->id)
            ->where('user_id', $participant->id)->update(['status' => 'revoked']);
        $this->assertSame(2, $this->execute('dry-run'));
        $this->assertSame(2, $this->execute('apply'));
        $this->assertDatabaseCount('learning_material_media', 0);
    }

    public function test_non_testing_environment_is_rejected_without_creating_fixture(): void
    {
        $this->createQaClass();
        $old = app()->environment();
        try {
            app()->instance('env', 'production');
            $this->assertSame(2, $this->execute('apply'));
        } finally {
            app()->instance('env', $old);
        }
        $this->assertSame(2, $this->execute('verify')); // missing fixture is never considered a successful verification
        $this->assertDatabaseCount('learning_material_media', 0);
    }
}
