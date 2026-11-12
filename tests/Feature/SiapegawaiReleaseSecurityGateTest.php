<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterialMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * REL-VERIFY-01B: cross-module access matrix on a dedicated, pre-migrated MySQL
 * *_testing database.  Transactions roll back fixture and state mutations.
 * This suite does NOT certify an actual browser/staging or disaster recovery.
 */
class SiapegawaiReleaseSecurityGateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $driver = (string) config('database.default');
        $database = (string) config('database.connections.'.$driver.'.database');
        if (! app()->environment('testing') || $driver !== 'mysql' || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException('REL-VERIFY-01B requires APP_ENV=testing, MySQL, and a *_testing database.');
        }
    }

    private function actor(string $role = 'participant', string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    public function test_guests_are_redirected_from_protected_member_and_admin_entry_points(): void
    {
        foreach ([
            'member.quick-quizzes.index', 'member.quick-quizzes.history',
            'member.classes.index', 'member.tryouts.index', 'member.profile.edit',
            'admin.participants.index', 'admin.quick-quizzes.index',
        ] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    public function test_member_and_administrator_routes_cannot_be_crossed_by_active_roles(): void
    {
        $participant = $this->actor();
        $operational = $this->actor('operational_admin');
        $root = $this->actor('superadmin');
        foreach (['admin.participants.index', 'admin.quick-quizzes.index'] as $name) {
            $this->actingAs($participant)->get(route($name))->assertForbidden();
        }
        foreach ([$operational, $root] as $admin) {
            foreach (['member.quick-quizzes.index', 'member.classes.index', 'member.tryouts.index'] as $name) {
                $this->actingAs($admin)->get(route($name))->assertForbidden();
            }
        }
        $this->actingAs($participant)->get(route('member.classes.index'))->assertOk();
        $this->actingAs($participant)->get(route('member.quick-quizzes.index'))->assertOk();
        $this->actingAs($operational)->get(route('admin.participants.index'))->assertOk();
    }

    public function test_pending_rejected_and_suspended_members_cannot_enter_quiz_class_or_tryout(): void
    {
        foreach (['pending', 'rejected', 'suspended'] as $status) {
            $member = $this->actor('participant', $status);
            foreach (['member.quick-quizzes.index', 'member.classes.index', 'member.tryouts.index'] as $name) {
                $this->actingAs($member)->get(route($name))->assertForbidden();
            }
        }
    }

    public function test_open_participant_session_rechecks_status_and_role_across_domains(): void
    {
        $member = $this->actor();
        $this->actingAs($member)->get(route('member.quick-quizzes.index'))->assertOk();
        DB::table('users')->where('id', $member->id)->update(['status' => 'suspended', 'suspended_at' => now()]);
        foreach (['member.quick-quizzes.index', 'member.classes.index', 'member.tryouts.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        DB::table('users')->where('id', $member->id)->update(['status' => 'active', 'suspended_at' => null]);
        $this->get(route('member.quick-quizzes.index'))->assertOk();
        DB::table('users')->where('id', $member->id)->update(['role' => 'operational_admin']);
        $this->get(route('member.quick-quizzes.index'))->assertForbidden();
    }

    public function test_existing_admin_session_loses_access_after_role_or_status_change(): void
    {
        $admin = $this->actor('operational_admin');
        $this->actingAs($admin)->get(route('admin.participants.index'))->assertOk();
        DB::table('users')->where('id', $admin->id)->update(['role' => 'participant']);
        $this->get(route('admin.participants.index'))->assertForbidden();
        DB::table('users')->where('id', $admin->id)->update([
            'role' => 'operational_admin', 'status' => 'suspended', 'suspended_at' => now(),
        ]);
        $this->get(route('admin.participants.index'))->assertForbidden();
    }

    public function test_operational_admin_cannot_grant_packages_or_suspend_participants(): void
    {
        $admin = $this->actor('operational_admin');
        $member = $this->actor();
        $auditBefore = AuditLog::query()->count();
        $this->actingAs($admin)->post(route('admin.participants.plan', $member), [
            'member_plan_id' => 1, 'reason' => 'Uji perubahan paket tanpa kewenangan.', 'confirmed' => '1',
        ])->assertForbidden();
        $this->post(route('admin.participants.status', $member), [
            'action' => 'suspend', 'reason' => 'Uji penangguhan tanpa kewenangan.', 'confirmed' => '1',
        ])->assertForbidden();
        $this->assertSame('active', $member->fresh()->status);
        $this->assertSame($auditBefore, AuditLog::query()->count());
    }

    public function test_private_pdf_denies_guest_foreign_user_and_revoked_enrollment_then_restores_access(): void
    {
        Storage::fake('class_media_private');
        $admin = $this->actor('superadmin');
        $owner = $this->actor();
        $other = $this->actor();
        $class = LearningClass::query()->create([
            'title' => 'REL B Kelas Uji PDF',
            'slug' => 'rel-b-pdf-' . fake()->unique()->bothify('????????'),
            'description' => 'Kelas fixture untuk pemeriksaan kontrol akses dokumen privat.',
            'status' => 'open', 'created_by' => $admin->id,
        ]);
        $otherClass = LearningClass::query()->create([
            'title' => 'REL B Kelas Lain',
            'slug' => 'rel-b-other-' . fake()->unique()->bothify('????????'),
            'description' => 'Kelas lain untuk memastikan cross-class URL tidak diperbolehkan.',
            'status' => 'open', 'created_by' => $admin->id,
        ]);
        $material = $class->materials()->create([
            'title' => 'Materi PDF Uji', 'content' => 'Dokumen uji saja, bukan data peserta.',
            'sort_order' => 1, 'status' => 'draft',
        ]);
        ClassEnrollment::query()->create([
            'class_id' => $class->id, 'user_id' => $owner->id,
            'approved_by' => $admin->id, 'approved_at' => now(), 'status' => 'active',
        ]);
        $pdfFixture = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $this->actingAs($admin)->post(route('admin.classes.media.pdf', [$class, $material]), [
            'title' => 'Dokumen QA privat',
            'pdf' => UploadedFile::fake()->createWithContent('rel-qa.pdf', $pdfFixture),
        ])->assertRedirect();
        $media = LearningMaterialMedia::query()->latest('id')->firstOrFail();
        $material->update(['status' => 'published', 'published_at' => now()]);
        $disk = Storage::disk('class_media_private');
        $disk->assertExists($media->private_path);
        $digestBefore = hash('sha256', (string) $disk->get($media->private_path));
        $url = route('member.classes.material.media.pdf', [$class, $material, $media]);
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($owner)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $this->get($url)->headers->get('Cache-Control'));
        $this->get(route('member.classes.material.media.pdf', [$otherClass, $material, $media]))->assertNotFound();
        ClassEnrollment::query()->where('class_id', $class->id)->where('user_id', $owner->id)
            ->update(['status' => 'revoked']);
        $this->get($url)->assertNotFound();
        ClassEnrollment::query()->where('class_id', $class->id)->where('user_id', $owner->id)
            ->update(['status' => 'active']);
        $this->get($url)->assertOk();
        $this->assertSame($digestBefore, hash('sha256', (string) $disk->get($media->private_path)));
    }
}
