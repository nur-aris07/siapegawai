<?php
/**
 * REL-VERIFY-01D / C-03 — isolated post-restore Laravel feature smoke.
 * RUN EXPLICITLY ONLY, using `php artisan test tools/rel-verify/SiapegawaiRestoredAppUatTest.php`.
 * Never included in the regular phpunit.xml test suites.
 * Exercises GET and role/private-PDF authorization against the existing synthetic
 * restore DB; all DB fixture writes occur within one test transaction and roll back.
 * This is NOT independent-host restore, real-media parity, browser acceptance or release sign-off.
 */
declare(strict_types=1);

use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterialMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SiapegawaiRestoredAppUatTest extends TestCase
{
    private const RESTORE_DB = 'siapegawai_restore_verify_testing';
    private bool $inTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $connection = (string) config('database.default');
            $defaultDb = (string) config('database.connections.mysql.database');
            $host = (string) config('database.connections.mysql.host');
            $port = (string) config('database.connections.mysql.port');
            if (! app()->environment('testing') || $connection !== 'mysql'
                || $defaultDb !== 'siapegawai_testing' || $host !== '127.0.0.1'
                || $port !== '3307' || is_file(base_path('bootstrap/cache/config.php'))) {
                throw new \RuntimeException('STOP: run explicitly under PHPUnit testing / native 3307; no config cache.');
            }
            // PHPUnit normally boots on the dedicated test DB. Rebind before ANY
            // fixture write; fail closed if the server identity is not exact.
            config(['database.connections.mysql.database' => self::RESTORE_DB]);
            DB::purge('mysql');
            $identity = DB::connection('mysql')->selectOne('SELECT DATABASE() AS db_name, @@port AS db_port');
            if (! $identity || $identity->db_name !== self::RESTORE_DB || (int) $identity->db_port !== 3307) {
                throw new \RuntimeException('STOP: restore DB identity mismatch.');
            }
            $migrationCount = DB::table('migrations')->count();
            $probeCount = DB::table('sgp_rel_verify_probe')->count();
            $userCount = DB::table('users')->count();
            $classCount = DB::table('classes')->count();
            if ($migrationCount !== 20 || $probeCount !== 1 || $userCount !== 0 || $classCount !== 0) {
                throw new \RuntimeException('STOP: existing restore DB is not the pristine C-02 synthetic snapshot.');
            }
            DB::connection('mysql')->beginTransaction();
            $this->inTransaction = true;
        } catch (\Throwable $e) {
            DB::disconnect('mysql');
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->inTransaction) {
                DB::connection('mysql')->rollBack();
                $this->inTransaction = false;
                $users = DB::table('users')->count();
                $classes = DB::table('classes')->count();
                if ($users !== 0 || $classes !== 0) {
                    throw new \RuntimeException('STOP: fixture rows remained after transaction rollback.');
                }
            }
        } finally {
            DB::disconnect('mysql');
            parent::tearDown();
        }
    }

    public function test_restored_app_boots_guest_and_respects_member_admin_role_boundaries(): void
    {
        $this->get('/login')->assertOk();
        $this->get(route('member.classes.index'))->assertRedirect(route('login'));
        $this->get(route('admin.participants.index'))->assertRedirect(route('login'));

        $member = User::factory()->create(['role' => 'participant', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'operational_admin', 'status' => 'active']);

        $this->actingAs($member)->get(route('member.classes.index'))->assertOk();
        $this->actingAs($member)->get(route('member.quick-quizzes.index'))->assertOk();
        $this->actingAs($member)->get(route('admin.participants.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.participants.index'))->assertOk();
        $this->actingAs($admin)->get(route('member.classes.index'))->assertForbidden();
    }

    public function test_restored_app_private_pdf_authorizes_enrollment_and_revocation(): void
    {
        // Storage::fake writes ONLY to the test fake disk, never original private files.
        Storage::fake('class_media_private');
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'participant', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'participant', 'status' => 'active']);

        $class = LearningClass::query()->create([
            'slug' => 'rel-verify-01d-restore-synthetic',
            'title' => 'REL-VERIFY-01D Synthetic Class',
            'description' => 'Disposable transaction-only restored app UAT fixture',
            'status' => 'open',
            'created_by' => $admin->id,
        ]);
        $material = $class->materials()->create([
            'title' => 'Synthetic PDF Material',
            'content' => 'Only synthetic fixture, no user data',
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        ClassEnrollment::query()->create([
            'class_id' => $class->id,
            'user_id' => $member->id,
            'status' => 'active',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        $filename = 'rel-verify-01d/c03-synthetic.pdf';
        $pdfBytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        Storage::disk('class_media_private')->put($filename, $pdfBytes);
        $media = LearningMaterialMedia::query()->create([
            'material_id' => $material->id,
            'kind' => 'pdf',
            'title' => 'Private PDF fixture',
            'private_path' => $filename,
            'original_name' => 'c03-synthetic.pdf',
            'size_bytes' => strlen($pdfBytes),
            'sha256' => hash('sha256', $pdfBytes),
        ]);
        $route = route('member.classes.material.media.pdf', [$class, $material, $media]);

        $this->get($route)->assertRedirect(route('login'));
        $this->actingAs($other)->get($route)->assertNotFound();
        $this->actingAs($member)->get($route)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        ClassEnrollment::query()->where('class_id', $class->id)->where('user_id', $member->id)->update(['status' => 'revoked']);
        $this->get($route)->assertNotFound();
        ClassEnrollment::query()->where('class_id', $class->id)->where('user_id', $member->id)->update(['status' => 'active']);
        $this->get($route)->assertOk();
    }
}
