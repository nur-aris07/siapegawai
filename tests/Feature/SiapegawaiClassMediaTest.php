<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialMedia;
use App\Models\LearningMaterialProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiapegawaiClassMediaTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'participant', string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function aClass(User $admin): LearningClass
    {
        return LearningClass::query()->create([
            'title' => 'Kelas Media dan Dokumen Privat',
            'slug' => 'kelas-media-' . fake()->unique()->bothify('????????'),
            'description' => 'Kelas pengujian bab pembelajaran dan lampiran yang tidak boleh diakses publik.',
            'status' => 'open', 'created_by' => $admin->id,
        ]);
    }

    private function material(LearningClass $class, string $status = 'draft'): LearningMaterial
    {
        return $class->materials()->create([
            'title' => 'Materi Pengantar Berkas',
            'content' => 'Penjelasan dasar pengelolaan berkas untuk materi pembelajaran privat kelas.',
            'sort_order' => 1, 'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    private function enroll(LearningClass $class, User $member, User $admin): ClassEnrollment
    {
        return ClassEnrollment::query()->create([
            'class_id' => $class->id, 'user_id' => $member->id,
            'approved_by' => $admin->id, 'approved_at' => now(), 'status' => 'active',
        ]);
    }

    private function pdf(string $name = 'materi.pdf'): UploadedFile
    {
        // Valid PDF header, minimal body. Content is not exposed by a static public URL.
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
    }

    public function test_admin_can_upload_private_pdf_and_only_active_enrolled_participant_can_download_after_publish(): void
    {
        Storage::fake('class_media_private');
        $admin = $this->actor('operational_admin');
        $superadmin = $this->actor('superadmin');
        $member = $this->actor();
        $other = $this->actor();
        $class = $this->aClass($admin);
        $material = $this->material($class);
        $this->enroll($class, $member, $superadmin);

        $url = route('admin.classes.media.pdf', [$class, $material]);
        $this->actingAs($admin)->post($url, ['title' => 'PDF TWK Bab Satu', 'pdf' => $this->pdf()])->assertRedirect();
        $media = LearningMaterialMedia::query()->sole();
        $this->assertSame('pdf', $media->kind);
        $this->assertNotNull($media->sha256);
        Storage::disk('class_media_private')->assertExists($media->private_path);
        $download = route('member.classes.material.media.pdf', [$class, $material, $media]);
        $this->actingAs($member)->get($download)->assertNotFound(); // draft not visible
        $this->actingAs($admin)->get(route('admin.classes.media.download', [$class, $material, $media]))->assertOk();

        $this->post(route('admin.classes.material.publish', [$class, $material]))->assertRedirect();
        $this->actingAs($other)->get($download)->assertNotFound();
        $response = $this->actingAs($member)->get($download)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->get(route('member.classes.show', $class))->assertOk()->assertSee('Unduh PDF privat');

        ClassEnrollment::query()->where('user_id', $member->id)->update(['status' => 'revoked']);
        $this->get($download)->assertNotFound();
        $this->assertDatabaseCount('learning_material_media', 1);
        ClassEnrollment::query()->where('user_id', $member->id)->update(['status' => 'active']);
        $this->get($download)->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'class.media_added', 'subject_id' => $media->id]);
    }

    public function test_malicious_or_non_pdf_files_are_rejected_and_upload_is_admin_only(): void
    {
        Storage::fake('class_media_private');
        $admin = $this->actor('operational_admin');
        $member = $this->actor();
        $class = $this->aClass($admin);
        $material = $this->material($class);
        $url = route('admin.classes.media.pdf', [$class, $material]);

        $this->actingAs($member)->post($url, ['title' => 'Lampiran test', 'pdf' => $this->pdf()])->assertForbidden();
        $this->actingAs($admin)->post($url, [
            'title' => 'Berkas palsu', 'pdf' => UploadedFile::fake()->createWithContent('fake.pdf', '<html>invalid file</html>'),
        ])->assertSessionHasErrors('pdf');
        $this->post($url, ['title' => 'Lampiran berbahaya',
            'pdf' => UploadedFile::fake()->createWithContent('fake.html', "%PDF-1.4\nTest")])->assertSessionHasErrors('pdf');
        $this->assertDatabaseCount('learning_material_media', 0);
    }

    public function test_wrong_class_and_other_participant_cannot_access_pdf(): void
    {
        Storage::fake('class_media_private');
        $admin = $this->actor('superadmin');
        $member = $this->actor();
        $class = $this->aClass($admin);
        $foreignClass = $this->aClass($admin);
        $material = $this->material($class);
        $this->actingAs($admin)->post(route('admin.classes.media.pdf', [$class, $material]),
            ['title' => 'PDF materi terbit', 'pdf' => $this->pdf()])->assertRedirect();
        $media = LearningMaterialMedia::query()->sole();
        $material->update(['status' => 'published', 'published_at' => now()]);
        $this->enroll($class, $member, $admin);
        $this->actingAs($member)->get(route('member.classes.material.media.pdf', [$foreignClass, $material, $media]))->assertNotFound();
        $this->get(route('member.classes.material.media.pdf', [$class, $material, $media]))->assertOk();
        $this->actingAs($this->actor('participant', 'suspended'))
            ->get(route('member.classes.material.media.pdf', [$class, $material, $media]))->assertForbidden();
    }

    public function test_pdf_and_video_cannot_be_added_to_published_material_and_old_progress_is_unchanged(): void
    {
        Storage::fake('class_media_private');
        $admin = $this->actor('superadmin');
        $member = $this->actor();
        $class = $this->aClass($admin);
        $material = $this->material($class, 'published');
        $this->enroll($class, $member, $admin);
        $this->actingAs($member)->post(route('member.classes.material.complete', [$class, $material]))->assertRedirect();

        $this->actingAs($admin)->post(route('admin.classes.media.pdf', [$class, $material]),
            ['title' => 'Lampiran setelah terbit', 'pdf' => $this->pdf()])->assertSessionHasErrors('material');
        $this->post(route('admin.classes.media.video', [$class, $material]), [
            'title' => 'Video terlarang', 'external_url' => 'https://youtu.be/abcdefghijk',
        ])->assertSessionHasErrors('material');
        $this->assertDatabaseCount('learning_material_media', 0);
        $this->assertSame(1, LearningMaterialProgress::query()->where('material_id', $material->id)->count());
        $this->actingAs($member)->get(route('member.classes.show', $class))->assertOk()->assertSee('1 dari 1 materi selesai');
    }

    public function test_video_link_restricts_hostname_https_and_safe_display(): void
    {
        $admin = $this->actor('operational_admin');
        $member = $this->actor();
        $class = $this->aClass($admin);
        $material = $this->material($class);
        $url = route('admin.classes.media.video', [$class, $material]);
        $this->actingAs($admin)->post($url, ['title' => 'Video sumber salah',
            'external_url' => 'https://youtube.com.bad-site.invalid/watch?v=abcdefghijk',
        ])->assertSessionHasErrors('external_url');
        $this->post($url, ['title' => 'Video sumber salah',
            'external_url' => 'http://www.youtube.com/watch?v=abcdefghijk',
        ])->assertSessionHasErrors('external_url');
        $this->post($url, ['title' => 'Video sumber tepercaya',
            'external_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        ])->assertRedirect();
        $media = LearningMaterialMedia::query()->sole();
        $this->assertSame('video_link', $media->kind);
        $material->update(['status' => 'published']);
        $this->enroll($class, $member, $admin);
        $this->actingAs($member)->get(route('member.classes.show', $class))->assertOk()
            ->assertSee('Buka video eksternal')->assertSee('noopener noreferrer nofollow');
    }

    public function test_chapter_membership_is_class_scoped_and_existing_materials_are_unchanged(): void
    {
        $admin = $this->actor('superadmin');
        $member = $this->actor();
        $class = $this->aClass($admin);
        $otherClass = $this->aClass($admin);
        $material = $this->material($class);
        $this->actingAs($admin)->post(route('admin.classes.chapters.store', $class), [
            'title' => 'Bab Konstitusi', 'sort_order' => 4,
        ])->assertRedirect();
        $chapter = $class->chapters()->sole();
        $this->put(route('admin.classes.material.update', [$otherClass, $material]), [
            'title' => $material->title, 'content' => $material->content,
            'sort_order' => 1, 'chapter_id' => $chapter->id,
        ])->assertNotFound();
        $this->post(route('admin.classes.material.store', $otherClass), [
            'title' => 'Bab asing', 'content' => $material->content, 'chapter_id' => $chapter->id,
        ])->assertSessionHasErrors('chapter_id');
        $this->put(route('admin.classes.material.update', [$class, $material]), [
            'title' => $material->title, 'content' => $material->content,
            'sort_order' => 1, 'chapter_id' => $chapter->id,
        ])->assertRedirect();
        $this->assertSame($chapter->id, $material->fresh()->chapter_id);
        $material->update(['status' => 'published']);
        $this->enroll($class, $member, $admin);
        $this->actingAs($member)->get(route('member.classes.show', $class))->assertOk()->assertSee('Bab Konstitusi');
    }

    public function test_removing_draft_pdf_deletes_storage_and_audits_without_erasing_material(): void
    {
        Storage::fake('class_media_private');
        $admin = $this->actor('superadmin');
        $class = $this->aClass($admin);
        $material = $this->material($class);
        $this->actingAs($admin)->post(route('admin.classes.media.pdf', [$class, $material]),
            ['title' => 'PDF untuk dihapus', 'pdf' => $this->pdf()])->assertRedirect();
        $media = LearningMaterialMedia::query()->sole();
        $path = $media->private_path;
        $this->post(route('admin.classes.media.remove', [$class, $material, $media]), ['confirmed' => '1'])->assertRedirect();
        $this->assertDatabaseCount('learning_material_media', 0);
        Storage::disk('class_media_private')->assertMissing($path);
        $this->assertDatabaseHas('learning_materials', ['id' => $material->id, 'status' => 'draft']);
        $this->assertSame(1, AuditLog::query()->where('action', 'class.media_removed')->count());
    }
}
