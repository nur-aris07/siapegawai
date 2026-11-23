<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\QuestionVersionMedia;
use App\Models\Topic;
use App\Models\User;
use App\Services\QuestionVersionCanonicalizer;
use App\Services\QuestionVersionMediaService;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SiapegawaiQuestionVersionMediaFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAIAAADZSiLoAAAAIklEQVR42g3GMQEAMBCEsFdy1hDGgtQ2U26T2bwhGH9Jlg+gIwwiwiu29wAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
        Storage::fake('question_media_private');
    }

    private function draft(): QuestionVersion
    {
        $question = Question::query()->create([
            'topic_id' => Topic::query()->firstOrFail()->id,
            'code' => 'P1-'.bin2hex(random_bytes(5)),
            'status' => 'draft',
        ]);
        $version = $question->versions()->create([
            'version_number' => 1, 'body' => 'Baca gambar berikut',
            'explanation' => 'Pembahasan independen', 'review_status' => 'draft',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            $version->options()->create([
                'label' => $label, 'body' => 'Pilihan '.$label,
                'is_correct' => $label === 'B', 'sort_order' => $i + 1,
            ]);
        }
        return $version;
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('from-user.png', base64_decode(self::PNG_BASE64, true));
    }

    private function operator(string $role = 'operational_admin'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    public function test_private_root_path_detection_accepts_windows_and_posix_but_rejects_relative(): void
    {
        $method = new \ReflectionMethod(\App\Services\QuestionMediaStorageService::class, 'isAbsoluteLocalRoot');
        $this->assertTrue($method->invoke(null, 'E:\\NewChapter1\\project\\siapegawai\\storage\\app\\siapegawai-question-media'));
        $this->assertTrue($method->invoke(null, 'E:/NewChapter1/project/siapegawai/storage/app/siapegawai-question-media'));
        $this->assertTrue($method->invoke(null, '/var/www/siapegawai/storage/app/siapegawai-question-media'));
        $this->assertFalse($method->invoke(null, 'E:storage\\app'));
        $this->assertFalse($method->invoke(null, 'storage/app/siapegawai-question-media'));
        $this->assertFalse($method->invoke(null, '\\\\server\\share\\question-media'));
    }

    public function test_additive_schema_can_be_read_without_modifying_legacy_version_hash(): void
    {
        $version = $this->draft();
        $original = hash('sha256', 'legacy-constant');
        $version->update(['version_hash' => $original]);
        $this->assertDatabaseCount('question_version_media', 0);
        $this->assertSame($original, $version->fresh()->version_hash);
        $this->assertSame([], app(QuestionVersionMediaService::class)->safeMetadataForVersion($version));
    }

    public function test_text_only_canonical_hash_matches_the_legacy_publish_serialization(): void
    {
        $version = $this->draft()->load('options');
        $hashInput = [
            'body' => $version->body, 'explanation' => $version->explanation,
            'options' => $version->options->map(fn ($o) => [$o->label, $o->body, (bool) $o->is_correct])->values()->all(),
        ];
        $legacy = hash('sha256', json_encode($hashInput, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $canonical = app(QuestionVersionCanonicalizer::class);
        $this->assertSame($legacy, $canonical->hashTextOnly($version));
        $this->assertSame($legacy, $canonical->hashWithMedia($version));
    }

    public function test_internal_draft_upload_is_private_immutable_and_idempotent(): void
    {
        if (!function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD extension not present: image byte decoding is fail-closed.');
        }
        $version = $this->draft();
        $actor = $this->operator();
        $service = app(QuestionVersionMediaService::class);
        $first = $service->addToDraft($actor, $version, 'stem', 1, $this->png(), 'Ilustrasi latihan');
        $this->assertDatabaseCount('question_version_media', 1);
        $this->assertTrue(Storage::disk('question_media_private')->exists($first->object_key));
        $this->assertStringNotContainsString('object_key', json_encode($first->toArray(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('sha256', json_encode($first->toArray(), JSON_THROW_ON_ERROR));
        $second = $service->addToDraft($actor, $version->fresh(), 'stem', 1, $this->png(), 'Ilustrasi latihan');
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('question_version_media', 1);
        $hash = app(QuestionVersionCanonicalizer::class)->hashWithMedia($version->fresh());
        $this->assertSame($hash, app(QuestionVersionCanonicalizer::class)->hashWithMedia($version->fresh()));
        $this->assertNotSame(app(QuestionVersionCanonicalizer::class)->hashTextOnly($version->fresh()), $hash);
    }

    public function test_published_version_cannot_be_modified_through_media_service(): void
    {
        $version = $this->draft();
        $version->update(['review_status' => 'published', 'published_at' => now()]);
        $version->question()->update(['status' => 'published', 'current_version_id' => $version->id]);
        $this->expectException(ValidationException::class);
        app(QuestionVersionMediaService::class)->addToDraft($this->operator(), $version->fresh(), 'stem', 1, $this->png());
    }

    public function test_participant_is_forbidden_from_adding_media(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(QuestionVersionMediaService::class)->addToDraft(
            $this->operator('participant'), $this->draft(), 'stem', 1, $this->png()
        );
    }

    public function test_invalid_slot_and_fake_file_are_rejected(): void
    {
        $version = $this->draft();
        $actor = $this->operator();
        $service = app(QuestionVersionMediaService::class);
        try {
            $service->addToDraft($actor, $version, 'option_Z', 1, $this->png());
            $this->fail('Invalid slot accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slot', $e->errors());
        }
        try {
            $service->addToDraft($actor, $version, 'stem', 1, UploadedFile::fake()->createWithContent('wrong.png', '<?php echo 1;'));
            $this->fail('Fake image accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('media', $e->errors());
        }
        $this->assertDatabaseCount('question_version_media', 0);
    }

    public function test_draft_detach_does_not_delete_physical_bytes(): void
    {
        if (!function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD extension not present: image byte decoding is fail-closed.');
        }
        $version = $this->draft();
        $actor = $this->operator();
        $service = app(QuestionVersionMediaService::class);
        $media = $service->addToDraft($actor, $version, 'option_A', 1, $this->png());
        $key = $media->object_key;
        $service->detachFromDraft($actor, $media);
        $this->assertDatabaseMissing('question_version_media', ['id' => $media->id]);
        $this->assertTrue(Storage::disk('question_media_private')->exists($key));
        $inventory = $service->inventoryStorage($this->operator('superadmin'));
        $this->assertContains($key, $inventory['orphan_keys']);
        $this->assertSame([], $inventory['missing_or_corrupt_keys']);
    }

    public function test_database_rejects_duplicate_slot_position_even_with_different_keys(): void
    {
        $version = $this->draft();
        $makeRow = fn (string $hex): array => [
            'question_version_id' => $version->id,
            'slot' => 'stem', 'position' => 1,
            'object_key' => 'questions/'.substr($hex, 0, 2).'/'.str_repeat($hex, 48).'.png',
            'sha256' => str_repeat($hex, 64),
            'mime_type' => 'image/png', 'byte_size' => 72,
            'width_px' => 3, 'height_px' => 3,
        ];
        \Illuminate\Support\Facades\DB::table('question_version_media')->insert($makeRow('a'));
        $this->expectException(\Illuminate\Database\QueryException::class);
        \Illuminate\Support\Facades\DB::table('question_version_media')->insert($makeRow('b'));
    }

    public function test_database_constraint_prevents_duplicate_slots_and_unlisted_public_disk(): void
    {
        $this->assertArrayNotHasKey('question_media_private', config('filesystems.links'));
        $this->assertFalse(config('filesystems.disks.question_media_private.serve'));
        $this->assertSame('private', config('filesystems.disks.question_media_private.visibility'));
        $this->assertFalse(collect(\Illuminate\Support\Facades\Route::getRoutes())->contains(
            fn ($r) => str_contains($r->uri(), 'question-media')
        ));
    }
}
