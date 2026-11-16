<?php
namespace Tests\Feature;

use App\Models\ClassPractice;
use App\Models\LearningClass;
use App\Models\Question;
use App\Models\QuickQuiz;
use App\Models\Topic;
use App\Models\TryoutPackage;
use App\Models\User;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiBulkQuestionAssignmentTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(SiapegawaiSubjectsSeeder::class); }
    private function admin(): User { return User::factory()->create(['role'=>'superadmin','status'=>'active']); }
    private function question(int $n,bool $weighted=false): int
    {
        $q=Question::query()->create(['code'=>'BULK-'.str_pad((string)$n,4,'0',STR_PAD_LEFT),
            'topic_id'=>Topic::query()->firstOrFail()->id,'status'=>'published']);
        $v=$q->versions()->create(['version_number'=>1,'body'=>'Pertanyaan penting batch nomor '.$n,
            'explanation'=>'Pembahasan lengkap soal nomor '.$n,'review_status'=>'published','published_at'=>now()]);
        foreach (['A','B','C','D'] as $i=>$label) $v->options()->create([
            'label'=>$label,'body'=>'Pilihan '.$label.' untuk soal nomor '.$n,
            'is_correct'=>$i===0,'score_weight'=>$weighted?$i+1:null,'sort_order'=>$i+1]);
        $q->update(['current_version_id'=>$v->id]);return $v->id;
    }
    private function cat(): TryoutPackage
    {
        return TryoutPackage::query()->create(['code'=>'BULK-TRYOUT','name'=>'Tryout Batch Soal',
            'description'=>'Soal terbit yang diatur oleh operator.','duration_minutes'=>45,
            'max_attempts'=>2,'status'=>'draft','created_by'=>auth()->id()]);
    }
    private function endpoints(string $domain,int $id): array
    {
        return [route('admin.bulk-assignment.preview',['domain'=>$domain,'target'=>$id]),
            route('admin.bulk-assignment.confirm',['domain'=>$domain,'target'=>$id])];
    }
    public function test_cat_preview_does_not_write_and_confirm_preserves_order_with_replay(): void
    {
        $this->actingAs($this->admin());$a=$this->question(1);$b=$this->question(2);$c=$this->question(3);
        $target=$this->cat();$target->items()->create(['question_version_id'=>$a,'sort_order'=>1]);
        [$preview,$confirm]=$this->endpoints('cat',$target->id);
        $response=$this->postJson($preview,['version_ids'=>[$c,$b]])->assertOk()->assertJsonPath('can_confirm',true)
            ->assertJsonPath('total_after',3);
        $this->assertDatabaseCount('tryout_package_items',1);
        $token=$response->json('token');
        $this->postJson($confirm,['token'=>$token])->assertOk()->assertJsonPath('status','committed');
        $this->postJson($confirm,['token'=>$token])->assertOk()->assertJsonPath('status','already_applied');
        $this->assertSame([$a,$c,$b],$target->items()->orderBy('sort_order')->pluck('question_version_id')->map(fn($n)=>(int)$n)->all());
        $this->assertDatabaseHas('audit_logs',['subject_id'=>$target->id,'action'=>'tryout.update']);
    }
    public function test_invalid_selection_reports_errors_without_losing_other_valid_choices(): void
    {
        $this->actingAs($this->admin());$target=$this->cat();$a=$this->question(11);$b=$this->question(12,true);
        [$preview]=$this->endpoints('cat',$target->id);
        $this->postJson($preview,['version_ids'=>[$a,$a]])->assertOk()->assertJsonPath('can_confirm',false)
            ->assertJsonPath('invalid_count',1)->assertJsonPath('valid_count',1);
        $this->assertDatabaseCount('tryout_package_items',0);
        $this->postJson($preview,['version_ids'=>[$a,$b]])->assertOk()->assertJsonPath('can_confirm',true);
    }
    public function test_cat_stale_editor_update_fails_closed_without_overwrite(): void
    {
        $this->actingAs($this->admin());$target=$this->cat();$a=$this->question(21);$b=$this->question(22);
        [$preview,$confirm]=$this->endpoints('cat',$target->id);
        $token=$this->postJson($preview,['version_ids'=>[$a]])->json('token');
        $this->put(route('tryouts.admin.update',$target),['code'=>$target->code,'name'=>'Tryout Batch Berubah',
            'description'=>'Perubahan editor biasa','duration_minutes'=>45,'max_attempts'=>2,'version_ids'=>[$b]])->assertRedirect();
        $this->postJson($confirm,['token'=>$token])->assertStatus(409);
        $this->assertSame([$b],$target->items()->pluck('question_version_id')->map(fn($n)=>(int)$n)->all());
    }
    public function test_qq_weighted_question_is_invalid_and_eligible_question_can_append_to_existing_draft(): void
    {
        $this->actingAs($this->admin());$good=$this->question(31);$bad=$this->question(32,true);
        $quiz=QuickQuiz::query()->create(['code'=>'BULK-QUIZ','status'=>'draft','created_by'=>auth()->id()]);
        $quiz->versions()->create(['version_number'=>1,'title'=>'Quiz Kilat untuk batch','description'=>'Uji batch',
            'seconds_per_question'=>30,'status'=>'draft']);
        [$preview,$confirm]=$this->endpoints('qq',$quiz->id);
        $this->postJson($preview,['version_ids'=>[$good,$bad]])->assertOk()->assertJsonPath('invalid_count',1)->assertJsonPath('can_confirm',false);
        $token=$this->postJson($preview,['version_ids'=>[$good]])->assertOk()->json('token');
        $this->postJson($confirm,['token'=>$token])->assertOk();
        $this->assertSame(1,$quiz->draftVersion()->items()->count());
    }
    public function test_class_practice_appends_to_existing_draft_not_published(): void
    {
        $this->actingAs($admin=$this->admin());$version=$this->question(41);
        $class=LearningClass::query()->create(['title'=>'Kelas untuk tugas batch','slug'=>'kelas-batch',
            'description'=>'Pengujian batch Kelas','status'=>'open','created_by'=>$admin->id]);
        $practice=ClassPractice::query()->create(['class_id'=>$class->id,'title'=>'Latihan Kelas Batch',
            'duration_minutes'=>30,'max_attempts'=>1,'sort_order'=>0,'status'=>'draft','created_by'=>$admin->id]);
        [$preview,$confirm]=$this->endpoints('kelas',$practice->id);
        $token=$this->postJson($preview,['version_ids'=>[$version]])->assertOk()->json('token');
        $this->postJson($confirm,['token'=>$token])->assertOk();
        $this->assertSame($version,(int)$practice->items()->firstOrFail()->question_version_id);
        $this->assertDatabaseHas('audit_logs',['subject_id'=>$practice->id,'action'=>'class.practice_updated']);
    }
    public function test_guest_and_participant_cannot_access_search_preview_or_confirm(): void
    {
        $this->actingAs($this->admin());$target=$this->cat();$url=route('admin.bulk-assignment.search',['domain'=>'cat','target'=>$target->id]);
        [$preview,$confirm]=$this->endpoints('cat',$target->id);
        auth()->logout();$this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role'=>'participant','status'=>'active']));
        $this->get($url)->assertForbidden();$this->postJson($preview,['version_ids'=>[999]])->assertForbidden();
        $this->postJson($confirm,['token'=>'bad'])->assertForbidden();
    }
    public function test_editor_integration_has_page_selection_preview_and_no_inline_js(): void
    {
        $this->actingAs($this->admin());$target=$this->cat();
        $this->get(route('tryouts.admin.edit',$target))->assertOk()->assertSee('Tambahkan banyak soal')
            ->assertSee('Pilih halaman ini')->assertSee('Batalkan pilihan halaman')->assertSee('Periksa');
    }
    public function test_create_forms_use_bulk_selection_before_first_save_and_pin_in_given_order(): void
    {
        $admin=$this->admin();$this->actingAs($admin);
        $a=$this->question(501);$b=$this->question(502);
        $this->get(route('tryouts.admin.create'))->assertOk()->assertDontSee('SAAT BUAT BARU')->assertSee('Pilih halaman ini')->assertSee('Batalkan pilihan halaman');
        $this->post(route('tryouts.admin.store'),[
            'code'=>'BULK-NEW-CAT','name'=>'Tryout Baru Sekaligus Soal','description'=>'Uji create picker',
            'duration_minutes'=>45,'max_attempts'=>1,'version_ids'=>[$b,$a],
        ])->assertRedirect();
        $cat=TryoutPackage::query()->where('code','BULK-NEW-CAT')->firstOrFail();
        $this->assertSame([$b,$a],$cat->items()->orderBy('sort_order')->pluck('question_version_id')->map(fn($n)=>(int)$n)->all());
        $this->get(route('admin.quick-quizzes.create'))->assertOk()->assertDontSee('SAAT BUAT BARU')
            ->assertSee('Pilih halaman ini')->assertSee('Batalkan pilihan halaman');
        $this->post(route('admin.quick-quizzes.store'),[
            'code'=>'BULK-NEW-QQ','title'=>'Quiz Baru Sekaligus Soal','description'=>'Uji editor',
            'seconds_per_question'=>30,'version_ids'=>[$a,$b],
        ])->assertRedirect();
        $quiz=QuickQuiz::query()->where('code','BULK-NEW-QQ')->firstOrFail();
        $this->assertSame([$a,$b],$quiz->draftVersion()->items()->orderBy('sort_order')->pluck('question_version_id')->map(fn($n)=>(int)$n)->all());
        $class=LearningClass::query()->create(['title'=>'Kelas Pengujian Create Soal','slug'=>'kelas-create-soal',
            'description'=>'Validasi create atomik','status'=>'open','created_by'=>$admin->id]);
        $this->get(route('admin.class-practices.create',$class))->assertOk()->assertDontSee('SAAT BUAT BARU')
            ->assertSee('Pilih halaman ini')->assertSee('Batalkan pilihan halaman');
        $this->post(route('admin.class-practices.store',$class),[
            'title'=>'Latihan Baru Sekaligus Soal','description'=>'Create sekali','duration_minutes'=>30,
            'max_attempts'=>1,'sort_order'=>0,'version_ids'=>[$b,$a],
        ])->assertRedirect();
        $practice=ClassPractice::query()->where('class_id',$class->id)->firstOrFail();
        $this->assertSame([$b,$a],$practice->items()->orderBy('sort_order')->pluck('question_version_id')->map(fn($n)=>(int)$n)->all());
    }

    public function test_create_and_edit_routes_load_bulk_picker_assets_from_vite(): void
    {
        $this->actingAs($admin=$this->admin());
        $cat=$this->cat();
        $class=LearningClass::query()->create(['title'=>'Kelas Bukti Asset', 'slug'=>'kelas-bukti-asset',
            'description'=>'Memastikan halaman latihan memuat asset picker', 'status'=>'open', 'created_by'=>$admin->id]);
        $practice=ClassPractice::query()->create(['class_id'=>$class->id,'title'=>'Latihan Bukti Asset',
            'duration_minutes'=>30,'max_attempts'=>1,'sort_order'=>0,'status'=>'draft','created_by'=>$admin->id]);
        $quiz=QuickQuiz::query()->create(['code'=>'BULK-VITE-QUIZ','status'=>'draft','created_by'=>$admin->id]);
        $quiz->versions()->create(['version_number'=>1,'title'=>'Quiz Bukti Asset','description'=>'Validasi entrypoint',
            'seconds_per_question'=>30,'status'=>'draft']);
        foreach ([
            route('tryouts.admin.create'), route('tryouts.admin.edit',$cat),
            route('admin.quick-quizzes.create'), route('admin.quick-quizzes.edit',$quiz),
            route('admin.class-practices.create',$class), route('admin.class-practices.edit',[$class,$practice]),
        ] as $url) {
            $html=$this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('admin-bulk-assignment', $html, 'Vite asset missing: '.$url);
        }
    }

    public function test_editor_forms_keep_submit_near_settings_on_desktop_and_after_selection_on_mobile(): void
    {
        foreach ([
            'resources/views/admin/tryouts/form.blade.php',
            'resources/views/admin/quick-quizzes/editor.blade.php',
            'resources/views/admin/class-practices/form.blade.php',
        ] as $file) {
            $content=file_get_contents(base_path($file));
            $this->assertSame(1,substr_count($content,'sg-editor-desktop-actions'));
            $this->assertTrue(strpos($content,'sg-editor-desktop-actions') < strpos($content,"@include('admin.bulk-assignment.composer'"));
        }
        $picker=file_get_contents(base_path('resources/views/admin/bulk-assignment/picker.blade.php'));
        $this->assertStringContainsString('data-bulk-unselect-page',$picker);
        $this->assertStringContainsString('data-bulk-select-page',$picker);
        $this->assertStringContainsString('data-bulk-page',$picker);
        $this->assertStringContainsString('aria-label="Halaman sebelumnya"',$picker);
        $this->assertStringContainsString('aria-label="Halaman berikutnya"',$picker);
    }

    public function test_editor_search_is_paginated_and_keeps_authorization(): void
    {
        $this->actingAs($this->admin());
        for($i=601;$i<=626;$i++)$this->question($i);
        $first=$this->getJson(route('tryouts.admin.search').'?q=BULK-&page=1')->assertOk()->assertJsonPath('has_more',true);
        $second=$this->getJson(route('tryouts.admin.search').'?q=BULK-&page=2')->assertOk();
        $this->assertCount(25,$first->json('data'));
        $this->assertCount(1,$second->json('data'));
        $this->assertFalse($second->json('has_more'));
        $first->assertJsonPath('current_page',1)->assertJsonPath('last_page',2)->assertJsonPath('total_results',26);
        $second->assertJsonPath('current_page',2)->assertJsonPath('last_page',2);
    }
}
