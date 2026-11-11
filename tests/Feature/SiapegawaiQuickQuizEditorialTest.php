<?php
namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuickQuiz;
use App\Models\QuickQuizVersion;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiQuickQuizEditorialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
    }

    private function person(string $role='operational_admin', string $status='active'): User
    {
        return User::factory()->create(['role'=>$role,'status'=>$status]);
    }

    private function question(int $num, bool $weighted=false): int
    {
        $q=Question::query()->create([
            'code'=>'QQ-'.str_pad((string)$num,3,'0',STR_PAD_LEFT),
            'topic_id'=>Topic::query()->firstOrFail()->id,'status'=>'published',
        ]);
        $v=$q->versions()->create([
            'version_number'=>1,'body'=>'Pertanyaan untuk uji Quiz Kilat '.$num,
            'explanation'=>'Penjelasan lengkap jawaban untuk soal '.$num,
            'review_status'=>'published','published_at'=>now(),
        ]);
        foreach (['A','B','C','D'] as $i=>$label) {
            $v->options()->create([
                'label'=>$label,'body'=>'Opsi '.$label.' soal '.$num,
                'is_correct'=>$i===0,'score_weight'=>$weighted ? $i+1 : null,
                'sort_order'=>$i+1,
            ]);
        }
        $q->update(['current_version_id'=>$v->id]);
        return $v->id;
    }

    private function versions(int $start, int $count): array
    {
        $ids=[];
        for ($i=$start;$i<$start+$count;$i++) $ids[]=$this->question($i);
        return $ids;
    }

    private function payload(array $versions, string $title='Quiz Kilat TWK Dasar'): array
    {
        return ['code'=>'QQ-TWK-01','title'=>$title,'description'=>'Latihan dengan waktu tiap soal.',
            'seconds_per_question'=>30,'version_ids'=>$versions];
    }

    private function createQuiz(array $versions): QuickQuiz
    {
        $this->post(route('admin.quick-quizzes.store'),$this->payload($versions))->assertRedirect();
        return QuickQuiz::query()->firstOrFail();
    }

    public function test_admin_only_routes_reject_guests_participants_and_inactive_admin(): void
    {
        $url=route('admin.quick-quizzes.index');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->person('participant'))->get($url)->assertForbidden();
        $this->get(route('admin.quick-quizzes.create'))->assertForbidden();
        $this->actingAs($this->person('operational_admin','suspended'))->get($url)->assertForbidden();
        $this->actingAs($this->person())->get($url)->assertOk()->assertSee('Quiz Kilat');
    }

    public function test_admin_can_save_incomplete_draft_but_cannot_publish_fewer_than_thirty_questions(): void
    {
        $this->actingAs($this->person());
        $quiz=$this->createQuiz($this->versions(1,29));
        $this->assertSame('draft',$quiz->status);
        $this->assertSame(29,$quiz->draftVersion()->items()->count());
        $this->post(route('admin.quick-quizzes.publish',$quiz),['confirmed_review'=>1])->assertSessionHasErrors('publish');
        $this->assertNull($quiz->fresh()->current_version_id);
        $this->assertDatabaseHas('audit_logs',['subject_type'=>QuickQuiz::class,'subject_id'=>$quiz->id,'action'=>'qq.create']);
    }

    public function test_published_edition_pins_thirty_binary_questions_and_blocks_unauthorized_mutations(): void
    {
        $this->actingAs($this->person('superadmin'));
        $ids=$this->versions(1,30);
        $quiz=$this->createQuiz($ids);
        $this->post(route('admin.quick-quizzes.publish',$quiz),['confirmed_review'=>1])->assertRedirect();
        $v=$quiz->fresh()->currentVersion;
        $this->assertSame('published',$quiz->fresh()->status);
        $this->assertSame(1,$v->version_number);
        $this->assertSame(30,$v->items()->count());
        $this->assertSame(30,$v->seconds_per_question);
        $this->assertSame($ids,$v->items()->pluck('question_version_id')->map(fn($id)=>(int)$id)->all());
        $this->put(route('admin.quick-quizzes.update',$quiz),$this->payload($ids))->assertSessionHasErrors('title');
        $this->post(route('admin.quick-quizzes.publish',$quiz),['confirmed_review'=>1])->assertSessionHasErrors('publish');
    }

    public function test_new_revision_keeps_published_snapshot_immutable_and_has_stable_order(): void
    {
        $this->actingAs($this->person());
        $ids=$this->versions(1,30);
        $quiz=$this->createQuiz($ids);
        $this->post(route('admin.quick-quizzes.publish',$quiz),['confirmed_review'=>1])->assertRedirect();
        $old=$quiz->fresh()->currentVersion;
        $this->post(route('admin.quick-quizzes.revision.start',$quiz))->assertRedirect();
        $this->post(route('admin.quick-quizzes.revision.start',$quiz))->assertRedirect();
        $this->assertSame(2,$quiz->versions()->count());
        $next=$this->question(31);
        $newIds=array_merge(array_slice($ids,1),[$next]);
        $payload=$this->payload($newIds,'Quiz Kilat TWK Revisi Kedua');
        $payload['seconds_per_question']=45;
        $this->put(route('admin.quick-quizzes.update',$quiz),$payload)->assertRedirect();
        $this->assertSame($old->id,$quiz->fresh()->current_version_id);
        $this->assertSame($ids,$old->fresh()->items()->pluck('question_version_id')->map(fn($id)=>(int)$id)->all());
        $this->post(route('admin.quick-quizzes.publish',$quiz),['confirmed_review'=>1])->assertRedirect();
        $new=$quiz->fresh()->currentVersion;
        $this->assertNotEquals($old->id,$new->id);
        $this->assertSame(2,$new->version_number);
        $this->assertSame(45,$new->seconds_per_question);
        $this->assertSame($newIds,$new->items()->pluck('question_version_id')->map(fn($id)=>(int)$id)->all());
        $this->assertSame($ids,$old->fresh()->items()->pluck('question_version_id')->map(fn($id)=>(int)$id)->all());
    }

    public function test_weighted_question_and_duplicate_versions_are_rejected_by_server(): void
    {
        $this->actingAs($this->person());
        $bad=$this->question(1,true);
        $this->post(route('admin.quick-quizzes.store'),$this->payload([$bad]))->assertSessionHasErrors('version_ids');
        $this->assertDatabaseCount('quick_quizzes',0);
        $good=$this->question(2);
        $this->post(route('admin.quick-quizzes.store'),$this->payload([$good,$good]))->assertSessionHasErrors('version_ids.0');
        $this->assertDatabaseCount('quick_quizzes',0);
    }

    public function test_ajax_yajra_uses_same_url_and_search_can_find_question_versions(): void
    {
        $this->actingAs($this->person());
        $id=$this->question(5);
        $this->createQuiz([$id]);
        $this->withHeaders(['X-Requested-With'=>'XMLHttpRequest','Accept'=>'application/json'])
            ->get(route('admin.quick-quizzes.index').'?draw=1&start=0&length=10&search[value]=QQ-TWK')
            ->assertOk()->assertJsonStructure(['draw','recordsTotal','recordsFiltered','data']);
        $this->get(route('admin.quick-quizzes.search').'?q=QQ-005')->assertOk()
            ->assertJsonPath('data.0.version_id',$id);
    }

    public function test_publish_requires_confirmation_and_seconds_are_bounded(): void
    {
        $this->actingAs($this->person());
        $data=$this->payload([]);$data['seconds_per_question']=0;
        $this->post(route('admin.quick-quizzes.store'),$data)->assertSessionHasErrors('seconds_per_question');
        $quiz=$this->createQuiz([]);
        $this->post(route('admin.quick-quizzes.publish',$quiz))->assertSessionHasErrors('confirmed_review');
    }

    public function test_archive_preserves_published_version_and_requires_reason(): void
    {
        $this->actingAs($this->person());
        $quiz=$this->createQuiz($this->versions(1,30));
        $this->post(route('admin.quick-quizzes.publish',$quiz),['confirmed_review'=>1])->assertRedirect();
        $versionId=$quiz->fresh()->current_version_id;
        $this->post(route('admin.quick-quizzes.archive',$quiz),['reason'=>'short'])->assertSessionHasErrors('reason');
        $this->post(route('admin.quick-quizzes.archive',$quiz),['reason'=>'Publikasi lama diganti dengan konten baru.'])->assertRedirect();
        $this->assertSame('archived',$quiz->fresh()->status);
        $this->assertSame($versionId,$quiz->fresh()->current_version_id);
        $this->assertSame('published',QuickQuizVersion::query()->findOrFail($versionId)->status);
        $this->assertDatabaseHas('audit_logs',['action'=>'qq.archive','subject_id'=>$quiz->id]);
    }

    public function test_admin_html_escapes_untrusted_title_and_exposes_no_participant_attempt_route(): void
    {
        $this->actingAs($this->person());
        $quiz=$this->createQuiz([]);
        $this->put(route('admin.quick-quizzes.update',$quiz),
            ['title'=>'Judul <script>alert(1)</script>','seconds_per_question'=>20,'version_ids'=>[]])->assertRedirect();
        $this->get(route('admin.quick-quizzes.edit',$quiz))->assertOk()->assertDontSee('<script>alert(1)</script>',false);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('member.quick-quizzes.start'));
    }

    public function test_uppercase_code_is_normalized_and_duplicate_prevented_before_insert(): void
    {
        $this->actingAs($this->person());
        $this->post(route('admin.quick-quizzes.store'), $this->payload([]))->assertRedirect();
        $new = $this->payload([]);
        $new['code'] = 'qq-twk-01';
        $this->post(route('admin.quick-quizzes.store'), $new)->assertSessionHasErrors('code');
        $this->assertDatabaseCount('quick_quizzes', 1);
    }

    public function test_exactly_forty_items_publish_and_forty_one_is_rejected(): void
    {
        $this->actingAs($this->person());
        $ids = $this->versions(1, 41);
        $tooMany = $this->payload($ids);
        $this->post(route('admin.quick-quizzes.store'), $tooMany)->assertSessionHasErrors('version_ids');
        $this->assertDatabaseCount('quick_quizzes', 0);
        $quiz = $this->createQuiz(array_slice($ids, 0, 40));
        $this->post(route('admin.quick-quizzes.publish', $quiz), ['confirmed_review'=>1])->assertRedirect();
        $this->assertSame(40, $quiz->fresh()->currentVersion->items()->count());
    }

    public function test_editing_only_description_writes_an_audit_entry(): void
    {
        $this->actingAs($this->person());
        $quiz = $this->createQuiz([]);
        $data = $this->payload([]);
        $data['description'] = 'Penjelasan editorial diperbarui.';
        $this->put(route('admin.quick-quizzes.update', $quiz), $data)->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'subject_type'=>QuickQuiz::class, 'subject_id'=>$quiz->id, 'action'=>'qq.draft_update',
        ]);
    }
    public function test_same_underlying_question_cannot_be_pinned_twice_through_different_versions(): void
    {
        $this->actingAs($this->person());
        $first=$this->question(1);
        $quiz=$this->createQuiz([$first]);
        $question=\App\Models\QuestionVersion::query()->findOrFail($first)->question;
        $v2=$question->versions()->create([
            'version_number'=>2,'body'=>'Revisi pertanyaan',
            'explanation'=>'Pembahasan revisi','review_status'=>'published','published_at'=>now(),
        ]);
        foreach (['A','B','C','D'] as $i=>$label) {
            $v2->options()->create(['label'=>$label,'body'=>'Pilihan '.$label,
                'is_correct'=>$i===0,'sort_order'=>$i+1]);
        }
        $question->update(['current_version_id'=>$v2->id]);
        $this->put(route('admin.quick-quizzes.update',$quiz),$this->payload([$first,$v2->id]))
            ->assertSessionHasErrors('version_ids');
        $this->assertSame([$first],$quiz->fresh()->draftVersion()->items()->pluck('question_version_id')
            ->map(fn($id)=>(int)$id)->all());
    }

    public function test_new_draft_rejects_unpublished_or_missing_explanation(): void
    {
        $this->actingAs($this->person());
        $id=$this->question(1);
        \App\Models\QuestionVersion::query()->findOrFail($id)->update(['explanation'=>null]);
        $this->post(route('admin.quick-quizzes.store'),$this->payload([$id]))
            ->assertSessionHasErrors('version_ids');
        $this->assertDatabaseCount('quick_quizzes',0);
    }

}
