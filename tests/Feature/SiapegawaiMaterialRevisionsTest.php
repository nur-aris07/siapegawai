<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialProgress;
use App\Models\LearningMaterialVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiMaterialRevisionsTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'participant', string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function aClass(User $admin): LearningClass
    {
        return LearningClass::query()->create([
            'title' => 'Kelas Materi Berjenjang',
            'slug' => 'kelas-revision-'.fake()->unique()->bothify('????????'),
            'description' => 'Deskripsi kelas untuk menguji snapshot materi dan progres peserta.',
            'status' => 'open', 'created_by' => $admin->id,
        ]);
    }

    private function material(LearningClass $class): LearningMaterial
    {
        return $class->materials()->create([
            'title' => 'Dasar Persiapan CPNS',
            'content' => 'Isi materi edisi awal yang dapat dibaca peserta sebelum revisi disetujui.',
            'status' => 'draft', 'sort_order' => 1,
        ]);
    }

    private function enroll(LearningClass $class, User $member, User $admin): void
    {
        ClassEnrollment::query()->create([
            'class_id' => $class->id, 'user_id' => $member->id,
            'status' => 'active', 'approved_at' => now(), 'approved_by' => $admin->id,
        ]);
    }

    private function publishFirst(User $admin, LearningClass $class, LearningMaterial $material): void
    {
        $this->actingAs($admin)->post(route('admin.classes.material.publish', [$class, $material]))->assertRedirect();
    }

    private function revisionData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Dasar Persiapan CPNS Revisi',
            'content' => 'Ini adalah konten revisi edisi dua yang hanya tampil seusai diterbitkan admin.',
            'sort_order' => 2, 'chapter_id' => null,
        ], $overrides);
    }

    private function publishRevision(LearningClass $class, LearningMaterial $material): void
    {
        $this->post(route('admin.classes.material.revision.publish', [$class, $material]), [
            'confirmed' => '1', 'reason' => 'Penyelarasan isi materi untuk kurikulum terbaru.',
        ])->assertRedirect();
    }

    public function test_first_publication_creates_immutable_initial_snapshot(): void
    {
        $admin = $this->person('superadmin');
        $class = $this->aClass($admin);
        $material = $this->material($class);
        $this->publishFirst($admin, $class, $material);
        $this->assertSame(1, $material->fresh()->published_version_number);
        $version = LearningMaterialVersion::query()->sole();
        $this->assertSame('published', $version->status);
        $this->assertSame(1, $version->version_number);
        $this->assertSame($material->title, $version->title);
        $this->assertSame($material->content, $version->content);
        $this->assertDatabaseHas('audit_logs', ['action' => 'class.material_published', 'subject_id' => $material->id]);
    }

    public function test_draft_revision_is_private_until_confirmed_publication(): void
    {
        $admin = $this->person('operational_admin');
        $member = $this->person();
        $class = $this->aClass($admin);
        $material = $this->material($class);
        $this->publishFirst($admin, $class, $material);
        $this->enroll($class, $member, $admin);
        $start = route('admin.classes.material.revision.start', [$class, $material]);
        $this->actingAs($admin)->post($start)->assertRedirect();
        $this->post($start)->assertRedirect();
        $this->assertDatabaseCount('learning_material_versions', 2);
        $this->put(route('admin.classes.material.revision.update', [$class, $material]), $this->revisionData())->assertRedirect();

        $this->assertSame('Dasar Persiapan CPNS', $material->fresh()->title);
        $this->actingAs($member)->get(route('member.classes.show', $class))
            ->assertOk()->assertSee('Dasar Persiapan CPNS')->assertDontSee('Ini adalah konten revisi edisi dua');
        $this->actingAs($admin);
        $this->publishRevision($class, $material);
        $this->assertSame('Dasar Persiapan CPNS Revisi', $material->fresh()->title);
        $this->assertSame(2, $material->fresh()->published_version_number);
        $this->assertSame('Dasar Persiapan CPNS', LearningMaterialVersion::query()
            ->where('material_id', $material->id)->where('version_number', 1)->sole()->title);
        $this->actingAs($member)->get(route('member.classes.show', $class))
            ->assertOk()->assertSee('Dasar Persiapan CPNS Revisi')->assertSee('EDISI v2');
        $this->assertDatabaseHas('audit_logs', ['action' => 'class.material_revision_published', 'subject_id' => $material->id]);
    }

    public function test_completion_stays_on_original_version_after_publication_and_regrant(): void
    {
        $admin = $this->person('superadmin');
        $member = $this->person();
        $newMember = $this->person();
        $class = $this->aClass($admin);
        $material = $this->material($class);
        $this->publishFirst($admin, $class, $material);
        $this->enroll($class, $member, $admin);
        $this->enroll($class, $newMember, $admin);
        $this->actingAs($member)->post(route('member.classes.material.complete', [$class, $material]))->assertRedirect();
        $original = LearningMaterialProgress::query()->where('user_id', $member->id)->sole();
        $originalDate = $original->completed_at->toDateTimeString();
        $this->assertSame(1, $original->completed_version_number);

        $this->actingAs($admin)->post(route('admin.classes.material.revision.start', [$class, $material]))->assertRedirect();
        $this->put(route('admin.classes.material.revision.update', [$class, $material]), $this->revisionData())->assertRedirect();
        $this->publishRevision($class, $material);
        $same = $original->fresh();
        $this->assertSame($originalDate, $same->completed_at->toDateTimeString());
        $this->assertSame(1, $same->completed_version_number);
        $this->assertDatabaseCount('learning_material_progress', 1);
        $this->actingAs($member)->get(route('member.classes.show', $class))
            ->assertOk()->assertSee('1 dari 1 materi selesai')
            ->assertSee('Progres selesai Anda tetap tersimpan');

        ClassEnrollment::query()->where('user_id', $member->id)->update(['status' => 'revoked']);
        $this->get(route('member.classes.show', $class))->assertNotFound();
        ClassEnrollment::query()->where('user_id', $member->id)->update(['status' => 'active']);
        $this->get(route('member.classes.show', $class))->assertOk()->assertSee('1 dari 1 materi selesai');
        $this->actingAs($newMember)->post(route('member.classes.material.complete', [$class, $material]))->assertRedirect();
        $this->assertSame(2, LearningMaterialProgress::query()->where('user_id', $newMember->id)->sole()->completed_version_number);
        $this->assertDatabaseCount('learning_material_progress', 2);
    }

    public function test_revision_admin_authorization_and_cross_class_boundaries(): void
    {
        $admin = $this->person('operational_admin');
        $member = $this->person();
        $class = $this->aClass($admin);
        $foreign = $this->aClass($admin);
        $material = $this->material($class);
        $this->publishFirst($admin, $class, $material);
        $url = route('admin.classes.material.revision.start', [$class, $material]);
        auth()->logout();
        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs($member)->post($url)->assertForbidden();
        $this->actingAs($this->person('operational_admin', 'suspended'))->post($url)->assertForbidden();
        $this->actingAs($admin)->post(route('admin.classes.material.revision.start', [$foreign, $material]))->assertNotFound();
        $this->assertDatabaseCount('learning_material_versions', 1);
    }

    public function test_published_content_needs_confirmation_and_invalid_chapter_is_rejected(): void
    {
        $admin = $this->person('superadmin');
        $class = $this->aClass($admin);
        $foreign = $this->aClass($admin);
        $chapter = $foreign->chapters()->create(['title' => 'Bab dari kelas lain', 'sort_order' => 1]);
        $material = $this->material($class);
        $this->publishFirst($admin, $class, $material);
        $this->post(route('admin.classes.material.revision.start', [$class, $material]))->assertRedirect();
        $update = route('admin.classes.material.revision.update', [$class, $material]);
        $this->put($update, $this->revisionData(['chapter_id' => $chapter->id]))->assertSessionHasErrors('chapter_id');
        $this->put($update, $this->revisionData(['content' => 'too short']))->assertSessionHasErrors('content');
        $publish = route('admin.classes.material.revision.publish', [$class, $material]);
        $this->post($publish, ['reason' => 'Alasan untuk pembaruan materi'])->assertSessionHasErrors('confirmed');
        $this->post($publish, ['confirmed' => 1, 'reason' => 'Singkat'])->assertSessionHasErrors('reason');
        $this->assertSame(1, $material->fresh()->published_version_number);
        $this->put(route('admin.classes.material.update', [$class, $material]), $this->revisionData())
            ->assertSessionHasErrors('material');
    }

    public function test_repeated_publish_does_not_change_previous_versions_and_next_revision_is_three(): void
    {
        $admin = $this->person('superadmin');
        $class = $this->aClass($admin);
        $material = $this->material($class);
        $this->publishFirst($admin, $class, $material);
        $this->post(route('admin.classes.material.revision.start', [$class, $material]))->assertRedirect();
        $this->put(route('admin.classes.material.revision.update', [$class, $material]), $this->revisionData())->assertRedirect();
        $this->publishRevision($class, $material);
        $this->post(route('admin.classes.material.revision.publish', [$class, $material]), [
            'confirmed' => 1, 'reason' => 'Terbitkan versi yang sudah diterbitkan',
        ])->assertSessionHasErrors('material');
        $this->post(route('admin.classes.material.revision.start', [$class, $material]))->assertRedirect();
        $draft = LearningMaterialVersion::query()->where('status', 'draft')->sole();
        $this->assertSame(3, $draft->version_number);
        $this->assertSame('Dasar Persiapan CPNS', LearningMaterialVersion::query()
            ->where('material_id', $material->id)->where('version_number', 1)->sole()->title);
        $this->assertDatabaseCount('learning_material_versions', 3);
    }

    public function test_revision_output_remains_escaped_and_private_history_is_admin_only(): void
    {
        $admin = $this->person('superadmin');
        $member = $this->person();
        $class = $this->aClass($admin);
        $material = $this->material($class);
        $this->publishFirst($admin, $class, $material);
        $this->enroll($class, $member, $admin);
        $this->post(route('admin.classes.material.revision.start', [$class, $material]))->assertRedirect();
        $this->put(route('admin.classes.material.revision.update', [$class, $material]),
            $this->revisionData(['content' => '<script>alert(1)</script> is a deliberately untrusted content string.']))->assertRedirect();
        $this->publishRevision($class, $material);
        $this->actingAs($member)->get(route('member.classes.show', $class))
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->actingAs($admin)->get(route('admin.classes.edit', $class))
            ->assertOk()->assertSee('Riwayat edisi')->assertSee('Lihat teks historis');
    }

    public function test_legacy_published_fixture_receives_initial_snapshot_before_revision(): void
    {
        $admin = $this->person('superadmin');
        $class = $this->aClass($admin);
        $material = $this->material($class);
        // Simulates an imported published row with no version metadata.
        $material->update(['status' => 'published', 'published_at' => now()]);
        $this->assertSame(0, $material->fresh()->published_version_number);
        $this->actingAs($admin)->post(route('admin.classes.material.revision.start', [$class, $material]))->assertRedirect();
        $this->assertSame(1, $material->fresh()->published_version_number);
        $this->assertDatabaseHas('learning_material_versions', [
            'material_id' => $material->id, 'version_number' => 1, 'status' => 'published',
            'title' => 'Dasar Persiapan CPNS',
        ]);
        $this->assertDatabaseHas('learning_material_versions', [
            'material_id' => $material->id, 'version_number' => 2, 'status' => 'draft',
        ]);
    }

    public function test_published_media_links_remain_unchanged_when_text_version_changes(): void
    {
        $admin = $this->person('superadmin');
        $member = $this->person();
        $class = $this->aClass($admin);
        $material = $this->material($class);
        $this->actingAs($admin)->post(route('admin.classes.media.video', [$class, $material]), [
            'title' => 'Video pengantar pembelajaran',
            'external_url' => 'https://youtu.be/abcdefghijk',
        ])->assertRedirect();
        $this->publishFirst($admin, $class, $material);
        $this->enroll($class, $member, $admin);
        $this->actingAs($admin)->post(route('admin.classes.material.revision.start', [$class, $material]))->assertRedirect();
        $this->put(route('admin.classes.material.revision.update', [$class, $material]), $this->revisionData())->assertRedirect();
        $this->publishRevision($class, $material);
        $this->assertDatabaseCount('learning_material_media', 1);
        $this->assertDatabaseHas('learning_material_media', [
            'material_id' => $material->id, 'kind' => 'video_link',
            'external_url' => 'https://youtu.be/abcdefghijk',
        ]);
        $this->actingAs($member)->get(route('member.classes.show', $class))
            ->assertOk()->assertSee('Video pengantar pembelajaran');
        $this->assertDatabaseCount('learning_material_progress', 0);
    }

}
