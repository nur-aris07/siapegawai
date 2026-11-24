<?php
namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\QuickQuiz;
use App\Models\Topic;
use App\Models\User;
use App\Services\QuestionEditorialMediaCoordinator;
use App\Services\QuickQuizEditorialService;
use App\Services\QuickQuizPinEligibility;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SiapegawaiQuickQuizFiguralEditorialCompatibilityTest extends TestCase
{
    use RefreshDatabase;
    private const PNG='iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAIAAADZSiLoAAAAIklEQVR42g3GMQEAMBCEsFdy1hDGgtQ2U26T2bwhGH9Jlg+gIwwiwiu29wAAAABJRU5ErkJggg==';
    private User $actor;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
        Storage::fake('question_media_private');
        config(['question_media.figural_publish_qa'=>true]);
        $this->actor=User::factory()->create(['role'=>'superadmin','status'=>'active']);
        $this->actingAs($this->actor);
    }
    private function text(string $code, int $count=4, mixed $weight=null): QuestionVersion
    {
        $q=Question::query()->create(['code'=>$code,'topic_id'=>Topic::query()->firstOrFail()->id,'status'=>'published']);
        $v=$q->versions()->create(['version_number'=>1,'body'=>'Pertanyaan sintetis '.$code,'explanation'=>'Penjelasan sintetis untuk pengujian.','review_status'=>'published','published_at'=>now()]);
        foreach(array_slice(['A','B','C','D','E'],0,$count) as $i=>$label) $v->options()->create(['label'=>$label,'body'=>'Opsi '.$label,'is_correct'=>$i===0,'score_weight'=>$weight,'sort_order'=>$i+1]);
        $q->update(['current_version_id'=>$v->id]);
        return $v;
    }
    private function upload(string $code, bool $mixed=false): Question
    {
        $slots=$mixed?['stem','explanation','option_A']:['stem','explanation','option_A','option_B','option_C','option_D','option_E'];
        $input=['draft_shell'=>1,'code'=>$code,'topic_id'=>Topic::query()->firstOrFail()->id,
            'body'=>$mixed?'Pertanyaan dengan diagram tambahan.':'','explanation'=>$mixed?'Pembahasan dengan diagram tambahan.':'',
            'option_a'=>'','option_b'=>$mixed?'Pilihan B':'','option_c'=>$mixed?'Pilihan C':'','option_d'=>$mixed?'Pilihan D':'','option_e'=>'','correct_option'=>$mixed?'A':'E',
            'media_files'=>array_map(fn()=>UploadedFile::fake()->createWithContent('figure.png',base64_decode(self::PNG,true)),$slots),
            'media_slots'=>$slots,'media_positions'=>array_fill(0,count($slots),1),'media_alts'=>array_fill(0,count($slots),'Gambar QA'),'media_captions'=>array_fill(0,count($slots),'')];
        $this->post(route('questions.admin.store'),$input)->assertRedirect()->assertSessionHasNoErrors();
        return Question::where('code',$code)->firstOrFail();
    }
    private function publishBank(Question $q): QuestionVersion
    {
        $this->post(route('questions.admin.publish',$q),['confirmed_review'=>1,'edit_token'=>app(QuestionEditorialMediaCoordinator::class)->editToken($q)])->assertRedirect()->assertSessionHasNoErrors();
        return $q->fresh()->currentVersion;
    }
    private function payload(array $ids,string $code='EC-QQ-01'): array
    {return ['code'=>$code,'title'=>'QQ sintetis editorial','description'=>'QA internal','seconds_per_question'=>30,'version_ids'=>$ids];}
    private function quiz(array $ids,string $code='EC-QQ-01'): QuickQuiz
    {return app(QuickQuizEditorialService::class)->create($this->payload($ids,$code),$this->actor);}
    private function reject(array $ids,?QuickQuiz $quiz=null): void
    {
        $quiz??=$this->quiz([],'EC-EMPTY');$before=$quiz->draftVersion()->items()->pluck('question_version_id')->all();
        try { app(QuickQuizEditorialService::class)->update($quiz,$this->payload($ids),$this->actor); $this->fail('Expected rejection'); }
        catch(ValidationException $e){$this->assertArrayHasKey('version_ids',$e->errors());}
        $this->assertSame($before,$quiz->draftVersion()->items()->pluck('question_version_id')->all());
    }
    public function test_normal_bank_image_only_update_and_real_qq_publish_thirty_with_no_filler_text(): void
    {
        $q=$this->upload('EC-IMAGE');$v=$q->latestVersion;
        // Real update contract: figural_draft + optimistic token; retained media arrays are optional if unchanged.
        $this->put(route('questions.admin.update',$q),['figural_draft'=>1,'edit_token'=>app(QuestionEditorialMediaCoordinator::class)->editToken($q),
            'code'=>$q->code,'topic_id'=>$q->topic_id,'body'=>'','explanation'=>'','option_a'=>'','option_b'=>'','option_c'=>'','option_d'=>'','option_e'=>'','correct_option'=>'E'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $v=$this->publishBank($q);$this->assertSame('', $v->body);$this->assertSame('', (string)$v->explanation);$this->assertCount(7,$v->media);
        $mixed=$this->publishBank($this->upload('EC-MIX',true));
        $ids=[$v->id,$mixed->id];for($i=0;$i<28;$i++)$ids[]=$this->text('EC-TEXT-'.$i,$i===0?5:4)->id;
        $this->post(route('admin.quick-quizzes.store'),$this->payload($ids))->assertRedirect()->assertSessionHasNoErrors();
        $quiz=QuickQuiz::where('code','EC-QQ-01')->firstOrFail();
        $this->post(route('admin.quick-quizzes.publish',$quiz),['confirmed_review'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $published=$quiz->fresh()->currentVersion;$this->assertSame($ids,$published->items()->pluck('question_version_id')->map(fn($x)=>(int)$x)->all());
        $before=$v->getAttributes();$media=$v->media->pluck('sha256')->all();
        app(QuickQuizEditorialService::class)->startRevision($quiz->fresh(),$this->actor);
        // Follow the actual UI action before editing retained option-E images.
        $this->post(route('questions.admin.start-revision',$q),['edit_token'=>app(QuestionEditorialMediaCoordinator::class)->editToken($q)])->assertRedirect()->assertSessionHasNoErrors();
        // Change bank content via real editor, not rewriting the old published version.
        $this->put(route('questions.admin.update',$q),['figural_draft'=>1,'edit_token'=>app(QuestionEditorialMediaCoordinator::class)->editToken($q),
            'code'=>$q->code,'topic_id'=>$q->topic_id,'body'=>'Konteks tambahan QA untuk revisi baru.','explanation'=>'','option_a'=>'','option_b'=>'','option_c'=>'','option_d'=>'','option_e'=>'','correct_option'=>'E'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $new=$this->publishBank($q->fresh());$this->assertNotEquals($v->id,$new->id);
        // Existing pin may remain; a newly created draft may not pin a stale version.
        $empty=$this->quiz([],'EC-STALE');$this->reject([$v->id],$empty);
        $this->reject([$v->id,$new->id],$quiz->fresh());
        app(QuickQuizEditorialService::class)->publish($quiz->fresh(),$this->actor);
        $this->assertSame($before,$v->fresh()->getAttributes());$this->assertSame($media,$v->fresh()->media->pluck('sha256')->all());
        $this->assertSame($ids,$published->items()->pluck('question_version_id')->map(fn($x)=>(int)$x)->all());
    }
    public function test_metadata_picker_pagination_and_bulk_share_figural_eligibility_without_private_fields(): void
    {
        $image=$this->publishBank($this->upload('EC-PICK-00'));$bad=$this->text('EC-PICK-01',4,0);$quiz=$this->quiz([]);
        for($i=2;$i<34;$i++)$this->text(sprintf('EC-PICK-%02d',$i));
        $url=route('admin.quick-quizzes.search').'?q=EC-PICK-';
        $first=$this->getJson($url.'&page=1')->assertOk()->assertJsonPath('total_results',34)->assertJsonPath('last_page',2)->assertJsonPath('eligibility_scope','CANDIDATES_METADATA_ONLY')->assertJsonPath('data.0.eligible',true)->assertJsonPath('data.1.eligible',false);
        $first->assertDontSee('object_key')->assertDontSee('sha256');
        $this->assertCount(30,$first->json('data'));$this->assertCount(4,$this->getJson($url.'&page=2')->assertOk()->json('data'));
        $base=['domain'=>'qq','target'=>$quiz->id];
        $this->getJson(route('admin.bulk-assignment.search',$base).'?q=EC-PICK-')->assertOk()->assertJsonPath('total_results',34)->assertJsonPath('last_page',3)->assertJsonPath('data.0.eligible',true)->assertJsonPath('data.1.eligible',false);
        $preview=route('admin.bulk-assignment.preview',$base);$confirm=route('admin.bulk-assignment.confirm',$base);
        $this->postJson($preview,['version_ids'=>[$image->id,$bad->id]])->assertOk()->assertJsonPath('can_confirm',false);
        $this->assertSame(0,$quiz->draftVersion()->items()->count());
        $token=$this->postJson($preview,['version_ids'=>[$image->id]])->assertOk()->assertJsonPath('can_confirm',true)->json('token');
        $this->postJson($confirm,['token'=>$token])->assertOk();$this->postJson($confirm,['token'=>$token])->assertOk()->assertJsonPath('status','already_applied');
        $this->assertSame(1,$quiz->draftVersion()->items()->count());
    }
    public function test_missing_and_corrupt_private_bytes_rejected_on_mutation_even_if_picker_metadata_valid(): void
    {
        $v=$this->publishBank($this->upload('EC-BYTES'));$quiz=$this->quiz([]);
        $m=$v->media->first();$disk=Storage::disk('question_media_private');$bytes=$disk->get($m->object_key);
        $disk->delete($m->object_key);$this->assertNull(app(QuickQuizPinEligibility::class)->reason($v,false));$this->reject([$v->id],$quiz);
        $disk->put($m->object_key,str_repeat('x',strlen($bytes)));$this->reject([$v->id],$quiz);
        $disk->put($m->object_key,$bytes);$this->assertNull(app(QuickQuizPinEligibility::class)->reason($v,true));
    }
    public function test_empty_slots_bad_labels_weights_keys_and_duplicate_pins_do_not_write(): void
    {
        $quiz=$this->quiz([]);$valid=$this->text('EC-VALID');
        $stem=$this->text('EC-STEM');$stem->update(['body'=>' ']);
        $ex=$this->text('EC-EX');$ex->update(['explanation'=>' ']);
        $opt=$this->text('EC-OPT');$opt->options()->where('label','D')->update(['body'=>' ']);
        $keys=$this->text('EC-KEY');$keys->options()->where('label','B')->update(['is_correct'=>true]);
        $three=$this->text('EC-3',3);$weighted=$this->text('EC-0',4,0);
        foreach([$stem,$ex,$opt,$keys,$three,$weighted] as $bad)$this->reject([$bad->id],$quiz);
        $this->reject([$valid->id,$valid->id],$quiz);
        $this->assertSame(0,$quiz->draftVersion()->items()->count());
    }
    public function test_bulk_confirm_rechecks_bytes_after_valid_preview(): void
    {
        $v=$this->publishBank($this->upload('EC-TOKEN'));$quiz=$this->quiz([]);
        $base=['domain'=>'qq','target'=>$quiz->id];
        $token=$this->postJson(route('admin.bulk-assignment.preview',$base),['version_ids'=>[$v->id]])->assertOk()->assertJsonPath('can_confirm',true)->json('token');
        $m=$v->media->first();$disk=Storage::disk('question_media_private');$bytes=$disk->get($m->object_key);
        $disk->delete($m->object_key);
        try {$this->postJson(route('admin.bulk-assignment.confirm',$base),['token'=>$token])->assertStatus(422)->assertJsonValidationErrors('version_ids');$this->assertSame(0,$quiz->draftVersion()->items()->count());}
        finally {$disk->put($m->object_key,$bytes);}
    }
    public function test_wrong_slot_and_foreign_metadata_cannot_supply_content(): void
    {
        $v=$this->publishBank($this->upload('EC-SLOTS'));$helper=app(QuickQuizPinEligibility::class);
        $foreign=$this->text('EC-FOREIGN');$m=$v->media->first();$m->question_version_id=$foreign->id;
        $this->assertSame('INVALID_MEDIA_REFERENCE',$helper->reason($v,false));
        $v=$v->fresh();$v->media->first()->slot='option_Z';$this->assertSame('INVALID_MEDIA_REFERENCE',$helper->reason($v,false));
        $v=$v->fresh();$v->media->first()->mime_type='image/jpeg';$this->assertSame('INVALID_MEDIA_REFERENCE',$helper->reason($v,false));
    }
}
