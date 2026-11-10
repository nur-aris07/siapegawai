<?php
namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\ClassPractice;
use App\Models\ClassPracticeAttempt;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\Question;
use App\Models\Topic;
use App\Models\TryoutAttempt;
use App\Models\User;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiClassPracticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
    }

    private function actor(string $role='participant', string $status='active'): User
    {
        return User::factory()->create(['role'=>$role,'status'=>$status]);
    }

    private function learningClass(User $actor): LearningClass
    {
        return LearningClass::query()->create([
            'title'=>'Kelas latihan TWK test', 'slug'=>'kelas-latihan-'.fake()->unique()->bothify('????????'),
            'description'=>'Kelas pembelajaran untuk pengujian latihan kelas peserta.',
            'status'=>'open', 'created_by'=>$actor->id,
        ]);
    }

    private function question(User $admin): Question
    {
        $this->actingAs($admin)->post(route('questions.admin.store'),[
            'code'=>'TWK-CP-01','topic_id'=>Topic::query()->firstOrFail()->id,
            'body'=>'Apa prinsip utama persatuan dalam kehidupan berbangsa?',
            'explanation'=>'Persatuan merupakan dasar menjaga kehidupan bersama dan menghormati hukum.',
            'option_a'=>'Mengutamakan individu',
            'option_b'=>'Menjaga persatuan bangsa',
            'option_c'=>'Menghindari tanggung jawab',
            'option_d'=>'Mengabaikan hukum',
            'option_e'=>'','correct_option'=>'B',
        ])->assertRedirect();
        $q=Question::query()->firstOrFail();
        $this->post(route('questions.admin.publish',$q),['confirmed_review'=>1])->assertRedirect();
        return $q->fresh();
    }

    private function prepare(int $max=2): array
    {
        $admin=$this->actor('superadmin');
        $class=$this->learningClass($admin);
        $material=$class->materials()->create([
            'title'=>'Pengenalan TWK', 'content'=>'Materi pengenalan wawasan kebangsaan untuk evaluasi di dalam kelas.',
            'status'=>'published', 'published_at'=>now(), 'sort_order'=>1,
        ]);
        $question=$this->question($admin);
        $this->post(route('admin.class-practices.store',$class),[
            'title'=>'Latihan Persatuan Bangsa', 'description'=>'Latihan internal kelas TWK.',
            'material_id'=>$material->id,'duration_minutes'=>5,'max_attempts'=>$max,'sort_order'=>1,
            'version_ids'=>[$question->current_version_id],
        ])->assertRedirect();
        $practice=ClassPractice::query()->firstOrFail();
        $this->post(route('admin.class-practices.publish',[$class,$practice]),['confirmed_review'=>1])->assertRedirect();
        return [$admin,$class,$material,$question,$practice->fresh()];
    }

    private function enroll(LearningClass $class, User $user, User $admin): ClassEnrollment
    {
        return ClassEnrollment::query()->create([
            'class_id'=>$class->id,'user_id'=>$user->id,'status'=>'active',
            'approved_by'=>$admin->id,'approved_at'=>now(),
        ]);
    }

    public function test_admin_can_publish_pinned_questions_and_archive_without_deleting_history(): void
    {
        [$admin,$class,$material,$question,$practice]=$this->prepare();
        $this->assertSame('published',$practice->status);
        $this->assertSame((int)$question->current_version_id,(int)$practice->items()->firstOrFail()->question_version_id);
        $this->assertNotNull($practice->items()->firstOrFail()->subject_code_snapshot);
        $this->assertDatabaseHas('audit_logs',['action'=>'class.practice_published','subject_id'=>$practice->id]);
        $this->actingAs($admin)->put(route('admin.class-practices.update',[$class,$practice]),[ 'title'=>'Penggantian ilegal' ])->assertForbidden();
        $this->post(route('admin.class-practices.archive',[$class,$practice]),['reason'=>'Materi perlu diperbarui untuk siklus berikutnya.'])->assertRedirect();
        $this->assertDatabaseHas('class_practices',['id'=>$practice->id,'status'=>'archived']);
        $this->assertDatabaseCount('class_practice_items',1);
    }

    public function test_participant_needs_active_enrollment_and_cannot_see_explanations_before_submit(): void
    {
        [$admin,$class,$material,$question,$practice]=$this->prepare();
        $user=$this->actor();
        $this->actingAs($user)->get(route('member.class-practices.index',$class))->assertNotFound();
        $this->post(route('member.class-practices.start',[$class,$practice]))->assertNotFound();
        $this->enroll($class,$user,$admin);
        $this->get(route('member.class-practices.index',$class))->assertOk()->assertSee($practice->title);
        $this->post(route('member.class-practices.start',[$class,$practice]))->assertRedirect();
        $attempt=ClassPracticeAttempt::query()->firstOrFail();
        $this->get(route('member.class-practices.take',[$class,$attempt]))->assertOk()
            ->assertSee('Apa prinsip utama persatuan')->assertDontSee('Kunci:')
            ->assertDontSee('Persatuan merupakan dasar menjaga kehidupan bersama');
        $this->get(route('member.class-practices.result',[$class,$attempt]))->assertRedirect();
    }

    public function test_autosave_resume_idempotent_submit_and_cat_history_isolation(): void
    {
        [$admin,$class,$material,$question,$practice]=$this->prepare();
        $user=$this->actor();$this->enroll($class,$user,$admin);
        $this->actingAs($user)->post(route('member.class-practices.start',[$class,$practice]))->assertRedirect();
        $this->post(route('member.class-practices.start',[$class,$practice]))->assertRedirect();
        $this->assertDatabaseCount('class_practice_attempts',1);
        $attempt=ClassPracticeAttempt::query()->firstOrFail();
        $item=$practice->items()->firstOrFail();
        $this->patchJson(route('member.class-practices.save',[$class,$attempt]),[
            'item_id'=>$item->id,'answer'=>'B','flagged'=>true,
        ])->assertOk()->assertJsonPath('answered',1);
        $this->get(route('member.class-practices.take',[$class,$attempt]))->assertOk()->assertSee('Apa prinsip utama persatuan');
        $this->post(route('member.class-practices.submit',[$class,$attempt]))->assertRedirect(route('member.class-practices.result',[$class,$attempt]));
        $this->post(route('member.class-practices.submit',[$class,$attempt]))->assertRedirect();
        $this->assertDatabaseHas('class_practice_attempts',['id'=>$attempt->id,'status'=>'completed','correct_count'=>1,'answered_count'=>1]);
        $this->get(route('member.class-practices.result',[$class,$attempt]))->assertOk()->assertSee('Pembahasan tiap soal')->assertSee('Kunci:');
        $this->patchJson(route('member.class-practices.save',[$class,$attempt]),[
            'item_id'=>$item->id,'answer'=>'A','flagged'=>false,
        ])->assertStatus(409);
        $this->assertDatabaseCount('class_practice_answers',1);
        $this->assertDatabaseCount('tryout_attempts',0);
        $this->assertDatabaseCount('tryout_attempt_answers',0);
        $this->assertDatabaseCount('learning_material_progress',0);
    }

    public function test_revocation_blocks_attempt_and_result_without_deleting_history_then_regrant_restores(): void
    {
        [$admin,$class,$material,$question,$practice]=$this->prepare();
        $user=$this->actor();$enrollment=$this->enroll($class,$user,$admin);
        $this->actingAs($user)->post(route('member.class-practices.start',[$class,$practice]))->assertRedirect();
        $attempt=ClassPracticeAttempt::query()->firstOrFail();
        $item=$practice->items()->firstOrFail();
        $this->patchJson(route('member.class-practices.save',[$class,$attempt]),[
            'item_id'=>$item->id,'answer'=>'B','flagged'=>false,
        ])->assertOk();
        $this->post(route('member.class-practices.submit',[$class,$attempt]))->assertRedirect();
        $enrollment->update(['status'=>'revoked']);
        $this->get(route('member.class-practices.index',$class))->assertNotFound();
        $this->get(route('member.class-practices.result',[$class,$attempt]))->assertNotFound();
        $this->post(route('member.class-practices.start',[$class,$practice]))->assertNotFound();
        $this->assertDatabaseCount('class_practice_attempts',1);
        $this->assertDatabaseCount('class_practice_answers',1);
        $enrollment->update(['status'=>'active']);
        $this->get(route('member.class-practices.result',[$class,$attempt]))->assertOk()->assertSee('100.00%');
    }

    public function test_wrong_user_cross_class_and_wrong_item_are_rejected(): void
    {
        [$admin,$class,$material,$question,$practice]=$this->prepare();
        $user=$this->actor();$other=$this->actor();
        $secondClass=$this->learningClass($admin);
        $this->enroll($class,$user,$admin);$this->enroll($class,$other,$admin);
        $this->actingAs($user)->post(route('member.class-practices.start',[$class,$practice]))->assertRedirect();
        $attempt=ClassPracticeAttempt::query()->firstOrFail();
        $item=$practice->items()->firstOrFail();
        $this->patchJson(route('member.class-practices.save',[$class,$attempt]),[
            'item_id'=>999999,'answer'=>'B','flagged'=>false,
        ])->assertUnprocessable();
        $this->patchJson(route('member.class-practices.save',[$class,$attempt]),[
            'item_id'=>$item->id,'answer'=>'E','flagged'=>false,
        ])->assertUnprocessable();
        $this->get(route('member.class-practices.take',[$secondClass,$attempt]))->assertNotFound();
        $this->actingAs($other)->get(route('member.class-practices.take',[$class,$attempt]))->assertForbidden();
        $this->patchJson(route('member.class-practices.save',[$class,$attempt]),[
            'item_id'=>$item->id,'answer'=>'B','flagged'=>false,
        ])->assertForbidden();
    }

    public function test_deadline_scores_unanswered_and_attempt_limit_prevents_new_session(): void
    {
        [$admin,$class,$material,$question,$practice]=$this->prepare(1);
        $user=$this->actor();$this->enroll($class,$user,$admin);
        $this->actingAs($user)->post(route('member.class-practices.start',[$class,$practice]))->assertRedirect();
        $attempt=ClassPracticeAttempt::query()->firstOrFail();
        $this->travel(6)->minutes();
        $this->get(route('member.class-practices.take',[$class,$attempt]))->assertRedirect(route('member.class-practices.result',[$class,$attempt]));
        $this->assertDatabaseHas('class_practice_attempts',['id'=>$attempt->id,'status'=>'timed_out','correct_count'=>0,'answered_count'=>0]);
        $this->post(route('member.class-practices.start',[$class,$practice]))->assertSessionHasErrors('practice');
        $this->assertDatabaseCount('class_practice_attempts',1);
        $this->travelBack();
    }

    public function test_archived_practice_allows_existing_attempt_to_finish_but_blocks_new_attempt(): void
    {
        [$admin,$class,$material,$question,$practice]=$this->prepare();
        $user=$this->actor();$this->enroll($class,$user,$admin);
        $this->actingAs($user)->post(route('member.class-practices.start',[$class,$practice]))->assertRedirect();
        $attempt=ClassPracticeAttempt::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.class-practices.archive',[$class,$practice]),['reason'=>'Latihan diganti setelah review konten.'])->assertRedirect();
        $this->actingAs($user)->get(route('member.class-practices.take',[$class,$attempt]))->assertOk();
        $this->post(route('member.class-practices.submit',[$class,$attempt]))->assertRedirect();
        $this->post(route('member.class-practices.start',[$class,$practice]))->assertSessionHasErrors('practice');
    }

    public function test_inactive_account_and_non_admin_cannot_manage_or_attempt(): void
    {
        [$admin,$class,$material,$question,$practice]=$this->prepare();
        $user=$this->actor();$this->enroll($class,$user,$admin);
        $this->actingAs($user)->get(route('admin.class-practices.index',$class))->assertForbidden();
        $this->actingAs($this->actor('participant','suspended'))
            ->get(route('member.class-practices.index',$class))->assertForbidden();
        $this->actingAs($this->actor('operational_admin'))
            ->get(route('admin.class-practices.index',$class))->assertOk();
    }

    public function test_publish_requires_review_confirmation_and_admin_permission(): void
    {
        $admin=$this->actor('superadmin');$class=$this->learningClass($admin);
        $question=$this->question($admin);
        $this->actingAs($admin)->post(route('admin.class-practices.store',$class),[
            'title'=>'Kelas soal valid','duration_minutes'=>30,'max_attempts'=>1,'sort_order'=>0,
            'version_ids'=>[$question->current_version_id],
        ])->assertRedirect();
        $practice=ClassPractice::query()->firstOrFail();
        $this->post(route('admin.class-practices.publish',[$class,$practice]),[])->assertSessionHasErrors('confirmed_review');
        $other=$this->actor();
        $this->actingAs($other)->post(route('admin.class-practices.publish',[$class,$practice]),['confirmed_review'=>1])->assertForbidden();
        $this->assertDatabaseHas('class_practices',['id'=>$practice->id,'status'=>'draft']);
    }

    public function test_pinned_version_keeps_historical_score_after_question_revision(): void
    {
        [$admin,$class,$material,$question,$practice]=$this->prepare();
        $item=$practice->items()->firstOrFail();
        $oldId=$item->question_version_id;
        $this->actingAs($admin)->put(route('questions.admin.update',$question),[
            'code'=>$question->code,'topic_id'=>$question->topic_id,
            'body'=>'Apa prinsip utama persatuan dalam kehidupan berbangsa?',
            'explanation'=>'Penjelasan revisi untuk soal lama dengan kunci jawaban baru.',
            'option_a'=>'Mengutamakan individu', 'option_b'=>'Menjaga persatuan bangsa',
            'option_c'=>'Menghindari tanggung jawab','option_d'=>'Mengabaikan hukum',
            'option_e'=>'','correct_option'=>'A',
        ])->assertRedirect();
        $this->post(route('questions.admin.publish',$question),['confirmed_review'=>1])->assertRedirect();
        $this->assertNotEquals($oldId,$question->fresh()->current_version_id);
        $this->assertSame((int)$oldId,(int)$practice->items()->firstOrFail()->question_version_id);
        $user=$this->actor();$this->enroll($class,$user,$admin);
        $this->actingAs($user)->post(route('member.class-practices.start',[$class,$practice]))->assertRedirect();
        $attempt=ClassPracticeAttempt::query()->firstOrFail();
        $this->patchJson(route('member.class-practices.save',[$class,$attempt]),[
            'item_id'=>$item->id,'answer'=>'B','flagged'=>false,
        ])->assertOk();
        $this->post(route('member.class-practices.submit',[$class,$attempt]))->assertRedirect();
        $this->assertSame(1,(int)$attempt->fresh()->correct_count);
    }

}
