<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiClassLearningProgressTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'participant', string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function makeClass(User $admin): LearningClass
    {
        return LearningClass::query()->create([
            'title' => 'Kelas Belajar TWK 2026',
            'slug' => 'kelas-belajar-twk-' . fake()->unique()->bothify('????????'),
            'description' => 'Kelas studi untuk pelatihan kompetensi dasar dan wawasan kebangsaan.',
            'status' => 'open',
            'created_by' => $admin->id,
        ]);
    }

    private function makeMaterial(LearningClass $class, string $status = 'published', string $title = 'Materi Pancasila'): LearningMaterial
    {
        return $class->materials()->create([
            'title' => $title,
            'content' => 'Ulasan pembelajaran Pancasila yang dapat digunakan peserta untuk latihan kelas.',
            'sort_order' => 1,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    private function enroll(LearningClass $class, User $user, User $admin, string $status = 'active'): ClassEnrollment
    {
        return ClassEnrollment::query()->create([
            'class_id' => $class->id,
            'user_id' => $user->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'status' => $status,
        ]);
    }

    public function test_completion_is_scoped_to_participant_and_idempotent_and_visible_in_both_pages(): void
    {
        $admin = $this->actor('superadmin');
        $member = $this->actor();
        $second = $this->actor();
        $class = $this->makeClass($admin);
        $first = $this->makeMaterial($class);
        $this->makeMaterial($class, 'published', 'Materi UUD 1945');
        $this->enroll($class, $member, $admin);
        $this->enroll($class, $second, $admin);

        $this->actingAs($member)->get(route('member.classes.show', $class))
            ->assertOk()->assertSee('0 dari 2 materi selesai');
        $endpoint = route('member.classes.material.complete', [$class, $first]);
        $this->post($endpoint)->assertRedirect(route('member.classes.show', $class));
        $this->post($endpoint)->assertRedirect(route('member.classes.show', $class));
        $this->assertSame(1, LearningMaterialProgress::query()->where('user_id', $member->id)->count());
        $this->get(route('member.classes.show', $class))->assertOk()
            ->assertSee('1 dari 2 materi selesai')->assertSee('50% selesai');
        $this->get(route('member.classes.index'))->assertOk()->assertSee('1 dari 2 materi');

        $this->actingAs($second)->get(route('member.classes.show', $class))
            ->assertOk()->assertSee('0 dari 2 materi selesai');
        $this->get(route('member.classes.index'))->assertOk()->assertSee('0 dari 2 materi');
    }

    public function test_draft_wrong_class_missing_enrollment_and_revoked_enrollment_cannot_mark(): void
    {
        $admin = $this->actor('superadmin');
        $member = $this->actor();
        $other = $this->actor();
        $class = $this->makeClass($admin);
        $published = $this->makeMaterial($class);
        $draft = $this->makeMaterial($class, 'draft', 'Materi Belum Terbit');
        $otherClass = $this->makeClass($admin);
        $this->enroll($class, $member, $admin);
        $endpoint = route('member.classes.material.complete', [$class, $published]);

        $this->actingAs($other)->post($endpoint)->assertNotFound();
        $this->actingAs($member)->post(route('member.classes.material.complete', [$class, $draft]))->assertNotFound();
        $this->post(route('member.classes.material.complete', [$otherClass, $published]))->assertNotFound();
        $this->assertDatabaseCount('learning_material_progress', 0);

        ClassEnrollment::query()->where('user_id', $member->id)->update(['status' => 'revoked']);
        $this->post($endpoint)->assertNotFound();
        $this->get(route('member.classes.show', $class))->assertNotFound();
        $this->assertDatabaseCount('learning_material_progress', 0);
    }

    public function test_progress_survives_revoke_and_regrant_but_is_hidden_while_revoked(): void
    {
        $admin = $this->actor('superadmin');
        $member = $this->actor();
        $class = $this->makeClass($admin);
        $material = $this->makeMaterial($class);
        $enrollment = $this->enroll($class, $member, $admin);
        $this->actingAs($member)->post(route('member.classes.material.complete', [$class, $material]))->assertRedirect();
        $enrollment->update(['status' => 'revoked']);
        $this->get(route('member.classes.index'))->assertDontSee('Kelas Belajar TWK 2026');
        $this->get(route('member.classes.show', $class))->assertNotFound();
        $enrollment->update(['status' => 'active']);
        $this->get(route('member.classes.show', $class))->assertOk()->assertSee('1 dari 1 materi selesai');
        $this->assertDatabaseCount('learning_material_progress', 1);
    }

    public function test_admin_can_edit_draft_with_audit_but_cannot_change_published_material(): void
    {
        $admin = $this->actor('operational_admin');
        $member = $this->actor();
        $class = $this->makeClass($admin);
        $draft = $this->makeMaterial($class, 'draft');
        $published = $this->makeMaterial($class, 'published', 'Materi Terbit');
        $fields = [
            'title' => 'Draft Sesudah Revisi',
            'content' => 'Isi materi yang telah diperbarui oleh admin untuk tinjauan sebelum publikasi.',
            'sort_order' => 7,
        ];
        $url = route('admin.classes.material.update', [$class, $draft]);
        $this->actingAs($member)->put($url, $fields)->assertForbidden();
        $this->actingAs($admin)->put($url, $fields)->assertRedirect();
        $this->assertSame('Draft Sesudah Revisi', $draft->fresh()->title);
        $this->assertSame(7, $draft->fresh()->sort_order);
        $this->assertSame(1, AuditLog::query()->where('action', 'class.material_draft_updated')->count());
        $this->put($url, $fields)->assertRedirect();
        $this->assertSame(1, AuditLog::query()->where('action', 'class.material_draft_updated')->count());

        $this->put(route('admin.classes.material.update', [$class, $published]), $fields)
            ->assertSessionHasErrors('material');
        $this->assertSame('Materi Terbit', $published->fresh()->title);
        $this->put(route('admin.classes.material.update', [$this->makeClass($admin), $draft]), $fields)->assertNotFound();
    }

    public function test_published_html_remains_escaped_with_progress_controls(): void
    {
        $admin = $this->actor('superadmin');
        $member = $this->actor();
        $class = $this->makeClass($admin);
        $material = $this->makeMaterial($class);
        $material->update(['content' => 'Teks kelas <script>alert(1)</script> tidak boleh dijalankan.']);
        $this->enroll($class, $member, $admin);
        $this->actingAs($member)->get(route('member.classes.show', $class))
            ->assertOk()->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Tandai materi selesai');
    }
}
