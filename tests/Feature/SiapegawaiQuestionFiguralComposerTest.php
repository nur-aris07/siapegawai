<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionVersionMedia;
use App\Models\Topic;
use App\Models\User;
use App\Services\QuestionEditorialMediaCoordinator;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SiapegawaiQuestionFiguralComposerTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAIAAADZSiLoAAAAIklEQVR42g3GMQEAMBCEsFdy1hDGgtQ2U26T2bwhGH9Jlg+gIwwiwiu29wAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
        Storage::fake('question_media_private');
    }

    private function admin(): User
    {
        return User::factory()->create(['role'=>'superadmin','status'=>'active']);
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('diagram.png',base64_decode(self::PNG,true));
    }

    private function content(string $code): array
    {
        return [
            'draft_shell'=>1,'code'=>$code,'topic_id'=>Topic::query()->firstOrFail()->id,
            'body'=>'Bagaimana membaca diagram pada gambar yang ditunjukkan dalam latihan ini?',
            'explanation'=>'Pembahasan lengkap mengenai hubungan dan pembacaan diagram.',
            'option_a'=>'Pilihan jawaban pertama','option_b'=>'Pilihan jawaban kedua',
            'option_c'=>'Pilihan jawaban ketiga','option_d'=>'Pilihan jawaban keempat',
            'option_e'=>'','correct_option'=>'A',
        ];
    }

    private function token(Question $question): string
    {
        return app(QuestionEditorialMediaCoordinator::class)->editToken($question);
    }

    public function test_create_can_save_text_and_multiple_linked_private_images_in_one_request(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD diperlukan');
        $this->actingAs($this->admin());
        $this->post(route('questions.admin.store'), $this->content('FIG-C02-MULTI') + [
            'media_files'=>[$this->png(),$this->png(),$this->png()],
            'media_slots'=>['stem','option_A','stem'],
            'media_positions'=>[1,1,2],
            'media_alts'=>['Diagram konteks','Opsi A','Diagram lanjutan'],
            'media_captions'=>['','',''],
        ])->assertRedirect();
        $q=Question::query()->where('code','FIG-C02-MULTI')->firstOrFail();
        $this->assertSame('draft',$q->status);
        $media=$q->latestVersion->media()->orderBy('slot')->orderBy('position')->get();
        $this->assertCount(3,$media);
        $this->assertSame(['option_A:1','stem:1','stem:2'], $media->map(fn($m)=>$m->slot.':'.$m->position)->all());
        foreach($media as $m) $this->assertTrue(Storage::disk('question_media_private')->exists($m->object_key));
        $this->get(route('questions.admin.edit',$q))->assertOk()->assertSee('data-fig-preview',false)
            ->assertSee('data-fig-composer',false)->assertSee('media_existing_ids[]',false);
    }

    public function test_edit_renders_only_one_hierarchical_media_composer_and_mobile_preview_disclosure(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD diperlukan');
        $this->actingAs($this->admin());
        $this->post(route('questions.admin.store'), $this->content('FIG-C03-HIERARCHY') + [
            'media_files'=>[$this->png()],
            'media_slots'=>['stem'], 'media_positions'=>[1],
            'media_alts'=>['Ilustrasi'], 'media_captions'=>[''],
        ])->assertRedirect();
        $question=Question::query()->where('code','FIG-C03-HIERARCHY')->firstOrFail();
        $this->get(route('questions.admin.edit',$question))->assertOk()
            ->assertSee('data-fig-hierarchy',false)
            ->assertSee('data-fig-group="stem"',false)
            ->assertSee('data-fig-group="option_A"',false)
            ->assertSee('data-fig-group="explanation"',false)
            ->assertSee('media_existing_slots[]',false)
            ->assertSee('data-fig-preview-disclosure',false)
            ->assertSee('data-fig-dropzone',false)
            ->assertSee('Pindahkan ke…')
            ->assertDontSee('Pengaturan gambar lanjutan (opsional)')
            ->assertDontSee('Unggah gambar ke draft')
            ->assertDontSee('name="slot"',false)
            ->assertDontSee('<select name="media_existing_slots[]"',false);
    }

    public function test_batch_attach_signature_matches_controller_and_keeps_position_metadata(): void
    {
        $method = new \ReflectionMethod(\App\Services\QuestionMediaBatchService::class, 'attach');
        $this->assertSame(
            ['question', 'actor', 'files', 'slots', 'positions', 'alts', 'captions'],
            array_map(static fn ($parameter) => $parameter->getName(), $method->getParameters())
        );
    }

    public function test_duplicate_batch_position_fails_before_question_creation(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD diperlukan');
        $this->actingAs($this->admin());
        $this->post(route('questions.admin.store'), $this->content('FIG-C02-DUP-POS') + [
            'media_files'=>[$this->png(), $this->png()],
            'media_slots'=>['stem','stem'], 'media_positions'=>[1,1],
            'media_alts'=>['A','B'], 'media_captions'=>['',''],
        ])->assertSessionHasErrors('media_positions');
        $this->assertDatabaseMissing('questions', ['code'=>'FIG-C02-DUP-POS']);
        $this->assertDatabaseCount('question_version_media', 0);
    }

    public function test_image_only_stem_options_and_explanation_save_as_private_draft(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD diperlukan');
        $this->actingAs($this->admin());
        $input = $this->content('FIG-C02-IMAGE-ONLY');
        foreach (['body','explanation','option_a','option_b','option_c','option_d','option_e'] as $key) {
            $input[$key] = '';
        }
        $input['correct_option'] = 'E';
        $slots = ['stem','option_A','option_B','option_C','option_D','option_E','explanation'];
        $this->post(route('questions.admin.store'), $input + [
            'media_files'=>array_map(fn ($slot) => $this->png(), $slots),
            'media_slots'=>$slots, 'media_positions'=>array_fill(0, count($slots), 1),
            'media_alts'=>array_fill(0, count($slots), 'Ilustrasi visual'),
            'media_captions'=>array_fill(0, count($slots), ''),
        ])->assertRedirect();
        $question=Question::query()->where('code','FIG-C02-IMAGE-ONLY')->firstOrFail();
        $this->assertSame('draft', $question->status);
        $version=$question->latestVersion;
        $this->assertSame('', $version->body);
        $this->assertSame('', $version->explanation);
        $this->assertSame(7, $version->media()->count());
        $this->assertSame('E', $version->options()->where('is_correct',true)->firstOrFail()->label);
        $this->assertSame('', $version->options()->where('label','E')->firstOrFail()->body);
    }

    public function test_invalid_second_file_rejects_entire_create_and_does_not_create_question(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('questions.admin.store'),$this->content('FIG-C02-BAD') + [
            'media_files'=>[$this->png(),UploadedFile::fake()->createWithContent('bad.pdf','%PDF-1.4 invalid')],
            'media_slots'=>['stem','option_A'],'media_positions'=>[1,1],
            'media_alts'=>['',''],'media_captions'=>['',''],
        ])->assertSessionHasErrors('media_files.1');
        $this->assertDatabaseMissing('questions',['code'=>'FIG-C02-BAD']);
        $this->assertDatabaseCount('question_version_media',0);
    }

    public function test_organize_existing_and_new_media_together_preserves_immutable_source_bytes(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD diperlukan');
        $this->actingAs($this->admin());
        $input=$this->content('FIG-C02-REORDER');
        $this->post(route('questions.admin.store'),$input + [
            'media_files'=>[$this->png(),$this->png()],
            'media_slots'=>['stem','stem'],'media_positions'=>[1,2],
            'media_alts'=>['Satu','Dua'],'media_captions'=>['',''],
        ])->assertRedirect();
        $q=Question::query()->where('code','FIG-C02-REORDER')->firstOrFail();
        $old=$q->latestVersion->media()->orderBy('position')->get();
        $oldKeys=$old->pluck('object_key')->all();
        $this->put(route('questions.admin.update',$q),array_merge($input,[
            'figural_draft'=>1,'edit_token'=>$this->token($q),
            'media_existing_ids'=>$old->pluck('id')->all(),
            'media_existing_slots'=>['option_B','stem'],
            'media_existing_positions'=>[1,2],
            'media_existing_alts'=>['Pindah B','Tetap stem'],
            'media_existing_captions'=>['',''],
            'media_existing_remove'=>['0','0'],
            'media_files'=>[$this->png()],
            'media_slots'=>['stem'],'media_positions'=>[1],
            'media_alts'=>['Baru'],'media_captions'=>[''],
        ]))->assertRedirect();
        $positions=$q->fresh()->latestVersion->media()->orderBy('slot')->orderBy('position')->get()
            ->map(fn($m)=>$m->slot.':'.$m->position)->all();
        $this->assertSame(['option_B:1','stem:1','stem:2'],$positions);
        foreach($oldKeys as $key) $this->assertTrue(Storage::disk('question_media_private')->exists($key));
    }

    public function test_stale_editor_token_cannot_change_attached_media(): void
    {
        if (!function_exists('imagecreatefromstring')) $this->markTestSkipped('GD diperlukan');
        $this->actingAs($this->admin());
        $input=$this->content('FIG-C02-STALE');
        $this->post(route('questions.admin.store'),$input + [
            'media_files'=>[$this->png()], 'media_slots'=>['stem'],'media_positions'=>[1],
            'media_alts'=>[''],'media_captions'=>[''],
        ])->assertRedirect();
        $q=Question::where('code','FIG-C02-STALE')->firstOrFail();
        $m=$q->latestVersion->media()->firstOrFail();
        $this->put(route('questions.admin.update',$q),array_merge($input,[
            'figural_draft'=>1,'edit_token'=>str_repeat('a',64),
            'media_existing_ids'=>[$m->id],'media_existing_slots'=>['option_B'],
            'media_existing_positions'=>[1],'media_existing_alts'=>[''],
            'media_existing_captions'=>[''],'media_existing_remove'=>['0'],
        ]))->assertSessionHasErrors('edit_token');
        $this->assertDatabaseHas('question_version_media',['id'=>$m->id,'slot'=>'stem']);
    }

    public function test_participant_cannot_create_with_batch_or_read_admin_previews(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'participant','status'=>'active']));
        $this->post(route('questions.admin.store'),$this->content('FIG-C02-DENIED') + [
            'media_files'=>[$this->png()],'media_slots'=>['stem'],'media_positions'=>[1],
            'media_alts'=>[''],'media_captions'=>[''],
        ])->assertForbidden();
        $this->assertDatabaseMissing('questions',['code'=>'FIG-C02-DENIED']);
    }
}
