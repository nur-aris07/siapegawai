<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionVersionMedia;
use App\Models\Topic;
use App\Models\User;
use App\Services\QuestionEditorialMediaCoordinator;
use App\Services\QuestionVersionCanonicalizer;
use App\Services\QuestionVersionMediaService;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiapegawaiQuestionFiguralEditorialTest extends TestCase
{
    use RefreshDatabase;

    // Decoded, CRC-valid 3x3 PNG fixture from the P1 C-04 focused regression.
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAIAAADZSiLoAAAAIklEQVR42g3GMQEAMBCEsFdy1hDGgtQ2U26T2bwhGH9Jlg+gIwwiwiu29wAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
        Storage::fake('question_media_private');
    }

    private function operator(string $role = 'superadmin', string $status = 'active'): User
    {
        return User::factory()->create(['role'=>$role, 'status'=>$status]);
    }

    private function input(string $code = 'FIG-P2-01'): array
    {
        return [
            'code'=>$code, 'topic_id'=>Topic::query()->firstOrFail()->id,
            'body'=>'Penjelasan tentang kedudukan konstitusi dalam negara Republik Indonesia?',
            'explanation'=>'Konstitusi mengatur dasar penyelenggaraan negara melalui aturan yang mengikat.',
            'option_a'=>'Menentukan struktur negara dan kewenangan',
            'option_b'=>'Mencatat laporan sekolah',
            'option_c'=>'Menyusun harga komoditas',
            'option_d'=>'Mengatur perusahaan dagang',
            'option_e'=>'', 'correct_option'=>'A',
        ];
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('figure.png', base64_decode(self::PNG_BASE64, true));
    }

    private function question(): Question
    {
        $this->post(route('questions.admin.store'), $this->input())->assertRedirect();
        return Question::query()->firstOrFail();
    }

    private function token(Question $q): string
    {
        return app(QuestionEditorialMediaCoordinator::class)->editToken($q);
    }

    private function add(Question $q, string $slot, int $position = 1): QuestionVersionMedia
    {
        return app(QuestionVersionMediaService::class)->addToDraft(
            auth()->user(), $q->fresh()->latestVersion, $slot, $position, $this->png(), 'Gambar latihan CPNS'
        );
    }

    public function test_create_shell_and_editor_media_ui_are_persistent_and_private(): void
    {
        $this->actingAs($this->operator());
        $this->post(route('questions.admin.store'), [
            'code'=>'FIG-SHELL', 'topic_id'=>Topic::firstOrFail()->id, 'draft_shell'=>1,
            'body'=>'', 'explanation'=>'', 'option_a'=>'', 'option_b'=>'',
            'option_c'=>'', 'option_d'=>'', 'correct_option'=>'',
        ])->assertRedirect();
        $q = Question::firstOrFail();
        $this->assertSame('draft', $q->status);
        $this->assertCount(4, $q->latestVersion->options);
        $this->get(route('questions.admin.edit', $q))
            ->assertOk()
            ->assertSee('Gambar & susunan', false)
            ->assertSee('data-fig-composer', false)
            ->assertSee('data-fig-hierarchy', false)
            ->assertSee('name="edit_token"', false)
            ->assertSee('image/jpeg,image/png,image/webp')
            ->assertDontSee('Tambahkan gambar pada draft')
            ->assertDontSee('Pengaturan gambar lanjutan (opsional)');
        $this->assertDatabaseCount('question_version_media', 0);
    }

    public function test_upload_preview_idempotency_and_foreign_or_guest_denial(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD required for media ingestion');
        $this->actingAs($this->operator());
        $q = $this->question();
        $this->post(route('questions.admin.media.upload', $q), [
            'edit_token'=>$this->token($q), 'slot'=>'stem', 'position'=>1,
            'media'=>$this->png(), 'alt_text'=>'Diagram soal',
        ])->assertRedirect();
        $m = QuestionVersionMedia::firstOrFail();
        $this->assertTrue(Storage::disk('question_media_private')->exists($m->object_key));
        $this->post(route('questions.admin.media.upload', $q), [
            'edit_token'=>$this->token($q), 'slot'=>'stem', 'position'=>1,
            'media'=>$this->png(), 'alt_text'=>'Diagram soal',
        ])->assertRedirect();
        $this->assertDatabaseCount('question_version_media', 1);
        $preview = route('questions.admin.media.preview', [$q,$m]);
        $this->get($preview)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('questions.admin.edit', $q))->assertOk()
            ->assertDontSee($m->object_key);
        $other = $this->operator();
        $this->actingAs($other)->get($preview)->assertOk(); // Active operators may audit all questions.
        $this->actingAs($this->operator('participant'))->get($preview)->assertForbidden();
        $this->app['auth']->guard()->logout();
        $this->get($preview)->assertRedirect('/login');
    }

    public function test_publish_image_only_a_to_d_is_qa_only_and_hash_is_media_aware(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD required for media ingestion');
        $this->actingAs($this->operator());
        $this->post(route('questions.admin.store'), [
            'code'=>'FIG-IMG-ONLY','topic_id'=>Topic::firstOrFail()->id,'draft_shell'=>1,
            'body'=>'','option_a'=>'','option_b'=>'','option_c'=>'','option_d'=>'',
            'correct_option'=>'A','explanation'=>'Penjelasan akademik tentang gambar dan jawaban yang paling tepat.',
        ])->assertRedirect();
        $q = Question::firstOrFail();
        foreach (['stem','option_A','option_B','option_C','option_D'] as $slot) $this->add($q,$slot);
        $this->post(route('questions.admin.publish', $q), [
            'confirmed_review'=>1, 'edit_token'=>$this->token($q),
        ])->assertSessionHasErrors('publish');
        $this->assertNull($q->fresh()->current_version_id);
        config()->set('question_media.figural_publish_qa', true);
        $this->post(route('questions.admin.publish', $q), [
            'confirmed_review'=>1, 'edit_token'=>$this->token($q),
        ])->assertRedirect();
        $q->refresh();
        $this->assertSame('published', $q->status);
        $this->assertCount(5, $q->currentVersion->media);
        $this->assertSame('', $q->currentVersion->options->firstWhere('label','A')->body);
        $this->assertSame(app(QuestionVersionCanonicalizer::class)->hashWithMedia($q->currentVersion), $q->currentVersion->version_hash);
    }

    public function test_missing_option_media_blocks_qa_publish_and_e_media_without_text_is_valid(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD required for media ingestion');
        $this->actingAs($this->operator());
        $q = $this->question();
        $this->add($q, 'option_E');
        $input = $this->input();
        $input['figural_draft'] = 1;
        $input['correct_option'] = 'E';
        $input['edit_token'] = $this->token($q);
        $this->put(route('questions.admin.update',$q),$input)->assertRedirect();
        config()->set('question_media.figural_publish_qa', true);
        $this->post(route('questions.admin.publish',$q), ['confirmed_review'=>1,'edit_token'=>$this->token($q)])->assertRedirect();
        $this->assertSame('E', $q->fresh()->currentVersion->options->firstWhere('is_correct', true)->label);
        $this->assertSame('', $q->fresh()->currentVersion->options->firstWhere('label','E')->body);
    }

    public function test_stale_token_rejects_update_and_detach_without_mutating_media(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD required for media ingestion');
        $this->actingAs($this->operator());
        $q = $this->question();
        $stale = $this->token($q);
        $m = $this->add($q,'stem');
        $input = $this->input();
        $input['edit_token'] = $stale;
        $input['figural_draft'] = 1;
        $input['body'] = 'Perubahan kedua yang tidak boleh menimpa form administrator lama.';
        $this->put(route('questions.admin.update',$q),$input)->assertSessionHasErrors('edit_token');
        $this->post(route('questions.admin.media.detach',[$q,$m]), [
            'edit_token'=>$stale,'confirmed_detach'=>1,
        ])->assertSessionHasErrors('edit_token');
        $this->assertDatabaseHas('question_version_media',['id'=>$m->id]);
        $this->assertNotSame($input['body'],$q->fresh()->latestVersion->body);
    }

    public function test_reorder_and_metadata_clone_immutable_bytes_but_not_historical_keys(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD required for media ingestion');
        $this->actingAs($this->operator());
        $q = $this->question();
        $a = $this->add($q,'stem',1);
        $b = $this->add($q,'stem',2);
        $oldKeys = [$a->object_key,$b->object_key];
        $this->post(route('questions.admin.media.reorder',$q), [
            'edit_token'=>$this->token($q),'slot'=>'stem','order'=>[$b->id,$a->id],
        ])->assertRedirect();
        $after = $q->fresh()->latestVersion->media()->orderBy('position')->get();
        $this->assertCount(2,$after);
        $this->assertNotContains($after[0]->object_key, $oldKeys);
        $this->assertSame($b->sha256, $after[0]->sha256);
        foreach ($oldKeys as $key) $this->assertTrue(Storage::disk('question_media_private')->exists($key));
        $this->post(route('questions.admin.media.metadata',[$q,$after[0]]), [
            'edit_token'=>$this->token($q),'alt_text'=>'Diagram yang dapat diakses','caption'=>'Keterangan baru',
        ])->assertRedirect();
        $this->assertSame('Keterangan baru',$q->fresh()->latestVersion->media()->orderBy('position')->first()->caption);
    }

    public function test_published_media_clone_revision_and_discard_preserve_original(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD required for media ingestion');
        $this->actingAs($this->operator());
        $q = $this->question();
        $old = $this->add($q,'stem');
        config()->set('question_media.figural_publish_qa', true);
        $this->post(route('questions.admin.publish',$q),['confirmed_review'=>1,'edit_token'=>$this->token($q)])->assertRedirect();
        $published = $q->fresh()->currentVersion;
        $hash = $published->version_hash;
        $this->post(route('questions.admin.start-revision',$q), ['edit_token'=>$this->token($q)])->assertRedirect();
        $draft = $q->fresh()->latestVersion;
        $this->assertSame('draft',$draft->review_status);
        $this->assertNotSame($published->id,$draft->id);
        $copy = $draft->media()->firstOrFail();
        $this->assertNotSame($old->object_key,$copy->object_key);
        $this->assertSame($old->sha256,$copy->sha256);
        $this->post(route('questions.admin.discard-draft',$q), [
            'confirmed_discard'=>1,'discard_reason'=>'Revisi ini tidak diperlukan lagi','edit_token'=>$this->token($q),
        ])->assertRedirect();
        $this->assertDatabaseMissing('question_version_media',['id'=>$copy->id]);
        $this->assertDatabaseHas('question_version_media',['id'=>$old->id]);
        $this->assertTrue(Storage::disk('question_media_private')->exists($copy->object_key));
        $this->assertSame($hash,$published->fresh()->version_hash);
        $this->assertSame($published->id,$q->fresh()->current_version_id);
    }

    public function test_invalid_file_and_foreign_media_are_rejected(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD required for media ingestion');
        $this->actingAs($this->operator());
        $q = $this->question();
        $this->post(route('questions.admin.media.upload',$q), [
            'edit_token'=>$this->token($q),'slot'=>'stem','position'=>1,
            'media'=>UploadedFile::fake()->createWithContent('bad.svg','<svg onload="alert(1)"></svg>'),
        ])->assertSessionHasErrors('media');
        $this->assertDatabaseCount('question_version_media',0);
        $otherInput = $this->input('FIG-OTHER');
        $this->post(route('questions.admin.store'),$otherInput)->assertRedirect();
        $other = Question::where('code','FIG-OTHER')->firstOrFail();
        $m = $this->add($q,'stem');
        $this->get(route('questions.admin.media.preview',[$other,$m]))->assertNotFound();
        $this->actingAs($this->operator('participant'));
        $this->post(route('questions.admin.media.detach',[$q,$m]), [
            'edit_token'=>$this->token($q),'confirmed_detach'=>1,
        ])->assertForbidden();
        $this->assertDatabaseHas('question_version_media',['id'=>$m->id]);
    }

    public function test_existing_text_only_hash_and_editor_compatibility_remain_unchanged(): void
    {
        $this->actingAs($this->operator());
        $q = $this->question();
        $this->post(route('questions.admin.publish',$q),['confirmed_review'=>1])->assertRedirect();
        $this->assertSame(
            app(QuestionVersionCanonicalizer::class)->hashTextOnly($q->fresh()->currentVersion),
            $q->fresh()->currentVersion->version_hash
        );
        $this->get(route('questions.admin.create'))->assertOk()->assertSee('Buat draft beserta gambar');
        $this->get(route('questions.admin.edit',$q))->assertOk()->assertSee('Mulai draft revisi');
    }
}
