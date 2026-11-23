<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\Topic;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SiapegawaiFigP4MediaRecoveryReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
        Storage::fake('question_media_private');
    }

    private function publishFixtureReference(): array
    {
        $question = Question::query()->create([
            'topic_id' => Topic::query()->firstOrFail()->id,
            'code' => 'FIG-P4-VERIFY-'.bin2hex(random_bytes(4)),
            'status' => 'published',
        ]);
        $version = QuestionVersion::query()->create([
            'question_id' => $question->id, 'version_number' => 1,
            'body' => 'Contoh audit soal figural QA',
            'explanation' => 'Contoh penjelasan QA',
            'review_status' => 'published', 'published_at' => now(),
            'version_hash' => str_repeat('b', 64),
        ]);
        $question->update(['current_version_id' => $version->id]);
        $bytes = file_get_contents(base_path('database/fixtures/fig-p3/pattern-stem.png'));
        $this->assertIsString($bytes);
        $size = getimagesizefromstring($bytes);
        $this->assertIsArray($size);
        $key = 'questions/aa/'.str_repeat('a',48).'.png';
        Storage::disk('question_media_private')->put($key, $bytes);
        DB::table('question_version_media')->insert([
            'question_version_id' => $version->id,
            'slot' => 'stem', 'position' => 1,
            'object_key' => $key, 'sha256' => hash('sha256', $bytes),
            'mime_type' => 'image/png', 'byte_size' => strlen($bytes),
            'width_px' => $size[0], 'height_px' => $size[1],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$key, $bytes];
    }

    private function runAudit(string $db = 'siapegawai_testing'): int
    {
        return Artisan::call('siapegawai:fig-p4-media-audit', [
            '--run' => true, '--expected-db' => $db,
        ]);
    }

    public function test_missing_fixture_is_blocked_instead_of_false_pass(): void
    {
        $this->assertSame(2, $this->runAudit());
        $this->assertStringContainsString('BLOCKED_NO_FIXTURE', Artisan::output());
    }

    public function test_reference_and_bytes_audit_is_select_only_and_digest_is_repeatable(): void
    {
        $this->publishFixtureReference();
        $writes = [];
        DB::listen(static function ($query) use (&$writes): void {
            if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $this->assertSame(0, $this->runAudit());
        $first = Artisan::output();
        $this->assertStringContainsString('PASS_REFERENCE_INTEGRITY_ONLY', $first);
        $this->assertMatchesRegularExpression('/Catalog digest SHA-256: [a-f0-9]{64}/', $first);
        $this->assertStringNotContainsString('questions/aa', $first);
        $this->assertSame(0, $this->runAudit());
        $this->assertSame($first, Artisan::output());
        $this->assertSame([], $writes, 'A media audit must never modify DB data.');
    }

    public function test_corruption_and_missing_object_fail_closed_without_repair(): void
    {
        [$key, $bytes] = $this->publishFixtureReference();
        Storage::disk('question_media_private')->put($key, 'tampered object');
        $this->assertSame(2, $this->runAudit());
        $this->assertStringContainsString('FAIL_INTEGRITY', Artisan::output());
        $this->assertSame('tampered object', Storage::disk('question_media_private')->get($key));
        Storage::disk('question_media_private')->delete($key);
        $this->assertSame(2, $this->runAudit());
        $this->assertStringContainsString('FAIL_INTEGRITY', Artisan::output());
        $this->assertFalse(Storage::disk('question_media_private')->exists($key));
    }

    public function test_allowlist_accepts_only_the_exact_isolated_v02_source_and_restore_identifiers(): void
    {
        $allowed = \App\Console\Commands\FigP4MediaIntegrityAudit::class;
        foreach (['siapegawai_testing','siapegawai_figp4_source',
            'siapegawai_figp4_restore_verify_testing'] as $database) {
            $this->assertTrue($allowed::isAllowedTestingDatabase($database));
        }
        foreach (['siapegawai','siapegawai_production','siapegawai_testing_typo',
            'siapegawai_figp4_source_copy','siapegawai_other_source',
            'siapegawai_figp4_restore_verify_testing_backup'] as $database) {
            $this->assertFalse($allowed::isAllowedTestingDatabase($database));
        }
    }

    public function test_wrong_database_identity_is_rejected_before_reference_reads(): void
    {
        $this->publishFixtureReference();
        $this->assertSame(2, $this->runAudit('siapegawai_production'));
        $this->assertSame(2, $this->runAudit('siapegawai_figp4_source')); // allowlisted, but not the CONNECTED DB
        // Fetch consumes the Artisan output buffer. Verify every property
        // against one captured response, not repeated empty reads.
        $diagnostic = Artisan::output();
        $this->assertStringContainsString('BLOCKED_DB_IDENTITY_MISMATCH', $diagnostic);
        $this->assertStringContainsString('--expected-db=siapegawai_testing', $diagnostic);
        $this->assertStringNotContainsString('questions/aa', $diagnostic);
        $this->assertSame(2, $this->runAudit('siapegawai_fig_restore_verify_testing'));
    }
}
