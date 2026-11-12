<?php

namespace Tests\Feature;

use App\Models\MemberPlan;
use App\Models\Question;
use App\Models\QuickQuiz;
use App\Models\QuickQuizAttempt;
use App\Models\QuickQuizQuotaPolicy;
use App\Models\User;
use App\Models\Topic;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiapegawaiQuickQuizAttemptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function member(string $role='participant',string $status='active'): User
    {
        return User::factory()->create(['role'=>$role,'status'=>$status]);
    }

    private function quota(?int $limit=3): void
    {
        $plan=MemberPlan::query()->where('code','PEJUANG')->firstOrFail();
        QuickQuizQuotaPolicy::query()->create([
            'member_plan_id'=>$plan->id,'code'=>'PLAN_PEJUANG','name'=>'Pejuang',
            'scope'=>'plan','daily_limit'=>$limit,'unlimited'=>false,'status'=>'active',
        ]);
    }

    private function published(string $code='QQ-EXAM-1',int $seconds=10): QuickQuiz
    {
        $quiz=QuickQuiz::query()->create(['code'=>$code,'status'=>'draft']);
        $v=$quiz->versions()->create(['version_number'=>1,'status'=>'published',
            'title'=>'Uji Quiz Kilat '.$code,'seconds_per_question'=>$seconds,'published_at'=>now()]);
        $topic=Topic::query()->firstOrFail();
        for ($i=1;$i<=30;$i++) {
            $q=Question::query()->create(['topic_id'=>$topic->id,'code'=>$code.'-'.$i,'status'=>'published']);
            $version=$q->versions()->create(['version_number'=>1,'body'=>'Isi soal '.$i,
                'explanation'=>'Pembahasan asli '.$i,'review_status'=>'published','published_at'=>now()]);
            foreach (['A','B','C','D'] as $order=>$label) {
                $version->options()->create(['label'=>$label,'body'=>'Pilihan '.$label,
                    'is_correct'=>$label==='A','score_weight'=>null,'sort_order'=>$order+1]);
            }
            $q->update(['current_version_id'=>$version->id]);
            $v->items()->create(['question_version_id'=>$version->id,'sort_order'=>$i,
                'subject_code_snapshot'=>'TWK','subject_name_snapshot'=>'TWK', 'topic_name_snapshot'=>'Topik uji']);
        }
        $quiz->update(['status'=>'published','current_version_id'=>$v->id]);
        return $quiz->fresh();
    }

    private function start(User $member, QuickQuiz $quiz, string $key='test-qq-start-key-000001'): QuickQuizAttempt
    {
        $this->actingAs($member)->post(route('member.quick-quizzes.start',$quiz),['start_key'=>$key])
            ->assertRedirect();
        return QuickQuizAttempt::query()->where('user_id',$member->id)->firstOrFail();
    }

    public function test_catalogue_reads_real_package_quota_without_creating_usage(): void
    {
        $this->quota(3);
        $quiz=$this->published();
        $this->actingAs($this->member())->get(route('member.quick-quizzes.index'))
            ->assertOk()->assertSee('Pejuang')->assertSee('3 percobaan')->assertSee('Uji Quiz Kilat');
        $this->get(route('member.quick-quizzes.show',$quiz))->assertOk()->assertSee('Mulai dan gunakan 1 kuota');
        $this->assertDatabaseCount('qq_quota_usages',0);
        $this->assertDatabaseCount('qq_daily_quota_counters',0);
    }

    public function test_unconfigured_quota_and_inactive_participants_cannot_start(): void
    {
        $quiz=$this->published();
        $member=$this->member();
        $this->actingAs($member)->get(route('member.quick-quizzes.index'))
            ->assertOk()->assertSee('Belum dikonfigurasi');
        $this->post(route('member.quick-quizzes.start',$quiz),['start_key'=>'missing-quota-key-000001'])
            ->assertSessionHasErrors('quota');
        $this->assertDatabaseCount('quick_quiz_attempts',0);
        $this->assertDatabaseCount('qq_quota_usages',0);
        $this->quota(1);
        $this->actingAs($this->member('participant','suspended'))
            ->post(route('member.quick-quizzes.start',$quiz),['start_key'=>'suspended-key-000001'])->assertForbidden();
        $this->actingAs($this->member('superadmin'))
            ->get(route('member.quick-quizzes.index'))->assertForbidden();
    }

    public function test_start_is_atomic_idempotent_and_different_keys_resume_single_active_attempt(): void
    {
        $this->quota(1); $quiz=$this->published(); $user=$this->member();
        $attempt=$this->start($user,$quiz);
        $this->assertSame('active',$attempt->status);
        $this->assertNotNull($attempt->qq_quota_usage_id);
        $this->assertDatabaseCount('qq_quota_usages',1);
        $this->actingAs($user)->post(route('member.quick-quizzes.start',$quiz),['start_key'=>'test-qq-start-key-000001'])
            ->assertRedirect(route('member.quick-quizzes.take',$attempt));
        $this->post(route('member.quick-quizzes.start',$quiz),['start_key'=>'another-tab-start-00001'])
            ->assertRedirect(route('member.quick-quizzes.take',$attempt));
        $this->assertDatabaseCount('qq_quota_usages',1);
        $this->assertDatabaseCount('quick_quiz_attempts',1);
    }

    public function test_answer_is_locked_feedback_has_published_explanation_and_does_not_leak_earlier(): void
    {
        $this->quota(2); $quiz=$this->published(); $user=$this->member(); $attempt=$this->start($user,$quiz);
        $item=$attempt->version->items()->firstOrFail();
        $this->get(route('member.quick-quizzes.take',$attempt))->assertOk()->assertSee('Isi soal 1')
            ->assertDontSee('Pembahasan asli 1')->assertDontSee('Kunci benar');
        $this->post(route('member.quick-quizzes.answer',$attempt),['item_id'=>$item->id,'answer'=>'B'])
            ->assertRedirect(route('member.quick-quizzes.take',$attempt));
        $this->assertDatabaseHas('quick_quiz_attempt_answers', ['quick_quiz_attempt_id'=>$attempt->id,
            'answer_label'=>'B','is_correct'=>false,'status'=>'answered']);
        $this->get(route('member.quick-quizzes.take',$attempt))->assertOk()
            ->assertSee('Kunci benar')->assertSee('Pembahasan asli 1');
        $this->post(route('member.quick-quizzes.answer',$attempt),['item_id'=>$item->id,'answer'=>'A'])->assertRedirect();
        $this->assertDatabaseCount('quick_quiz_attempt_answers',1);
        $this->assertDatabaseHas('quick_quiz_attempt_answers',['quick_quiz_attempt_id'=>$attempt->id,'answer_label'=>'B']);
        $this->post(route('member.quick-quizzes.next',$attempt))->assertSessionHasErrors('feedback');
        $this->travel(6)->seconds();
        $this->post(route('member.quick-quizzes.next',$attempt))->assertRedirect();
        $this->assertSame(1,$attempt->fresh()->current_index);
        $this->assertSame('question',$attempt->fresh()->phase);
    }


    public function test_ajax_answer_and_next_update_only_exam_fragment_and_preserve_no_js_fallback(): void
    {
        $this->quota(2); $quiz=$this->published(); $user=$this->member(); $attempt=$this->start($user,$quiz);
        $item=$attempt->version->items()->firstOrFail();
        $question=$this->getJson(route('member.quick-quizzes.take',$attempt))->assertOk()
            ->assertJsonPath('phase','question')->assertJsonPath('index',1);
        $this->assertStringContainsString('qq-answer-form', $question->json('html'));
        $this->assertStringNotContainsString('Kunci benar', $question->json('html'));

        $feedback=$this->postJson(route('member.quick-quizzes.answer',$attempt),
            ['item_id'=>$item->id,'answer'=>'B'])->assertOk()->assertJsonPath('phase','feedback');
        $html=$feedback->json('html');
        $this->assertStringContainsString('Isi soal 1',$html);
        $this->assertStringContainsString('qq-answer-choice--incorrect',$html);
        $this->assertStringContainsString('qq-answer-choice--correct',$html);
        $this->assertStringContainsString('Pembahasan asli 1',$html);
        $this->assertStringContainsString('Jawaban Anda · salah',$html);
        $this->assertSame('feedback',$attempt->fresh()->phase);
        $this->assertDatabaseCount('qq_quota_usages',1);

        // Five seconds is a SERVER guarantee, not only a JavaScript delay.
        $this->travel(4)->seconds();
        $this->postJson(route('member.quick-quizzes.next',$attempt),[])
            ->assertUnprocessable()->assertJsonValidationErrors('feedback');
        $this->travel(2)->seconds();
        $next=$this->postJson(route('member.quick-quizzes.next',$attempt),[])
            ->assertOk()->assertJsonPath('phase','question')->assertJsonPath('index',2);
        $this->assertStringContainsString('Isi soal 2',$next->json('html'));
        $this->assertStringNotContainsString('Pembahasan asli 2',$next->json('html'));
        $this->assertDatabaseCount('quick_quiz_attempt_answers',1);
        $this->assertDatabaseCount('qq_quota_usages',1);
        $this->get(route('member.quick-quizzes.take',$attempt))->assertOk()->assertSee('Isi soal 2');
    }

    public function test_ajax_feedback_stays_on_same_card_for_correct_answer_and_escapes_content(): void
    {
        $this->quota(1); $quiz=$this->published(); $user=$this->member(); $attempt=$this->start($user,$quiz);
        $item=$attempt->version->items()->firstOrFail();
        $item->version->update(['explanation'=>'<img src=x onerror=alert(1)>']);
        $html=$this->actingAs($user)->postJson(route('member.quick-quizzes.answer',$attempt),
            ['item_id'=>$item->id,'answer'=>'A'])->assertOk()->assertJsonPath('phase','feedback')->json('html');
        $this->assertStringContainsString('Jawaban benar',$html);
        $this->assertStringContainsString('Jawaban Anda · benar',$html);
        $this->assertStringContainsString('qq-answer-choice--correct',$html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;',$html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>',$html);
        $this->assertDatabaseCount('quick_quiz_attempt_answers',1);
    }

    public function test_timeout_does_not_consume_unissued_next_question_during_disconnection(): void
    {
        $this->quota(2); $quiz=$this->published('QQ-EXAM-2',5); $user=$this->member();
        $attempt=$this->start($user,$quiz);
        $this->travel(8)->seconds();
        $this->get(route('member.quick-quizzes.take',$attempt))->assertOk()
            ->assertSee('Waktu habis')->assertSee('Pembahasan asli 1');
        $this->assertDatabaseCount('quick_quiz_attempt_answers',1);
        $this->travel(14)->seconds();
        // State remains feedback until explicit acknowledgement; no unseen question timer is running.
        $this->get(route('member.quick-quizzes.take',$attempt))->assertOk()->assertSee('Pembahasan asli 1');
        $this->assertDatabaseCount('quick_quiz_attempt_answers',1);
        $this->post(route('member.quick-quizzes.next',$attempt))->assertRedirect();
        $this->assertSame(1,$attempt->fresh()->current_index);
        $this->assertTrue($attempt->fresh()->question_expires_at->greaterThan(now()));
        $this->assertDatabaseCount('quick_quiz_attempt_answers',1);
    }

    public function test_abandonment_after_recovery_window_keeps_quota_and_hides_unseen_questions(): void
    {
        $this->quota(2); $quiz=$this->published(); $user=$this->member(); $attempt=$this->start($user,$quiz);
        $this->travel(31)->minutes();
        $this->get(route('member.quick-quizzes.take',$attempt))->assertRedirect(route('member.quick-quizzes.result',$attempt));
        $this->assertSame('abandoned',$attempt->fresh()->status);
        $this->assertDatabaseCount('quick_quiz_attempt_answers',1);
        $this->get(route('member.quick-quizzes.result',$attempt))->assertOk()
            ->assertSee('Pembahasan asli 1')->assertDontSee('Pembahasan asli 2');
        $this->assertDatabaseCount('qq_quota_usages',1);
    }

    public function test_attempt_result_and_history_are_owned_and_separate_from_cat(): void
    {
        $this->quota(1); $quiz=$this->published(); $owner=$this->member();$other=$this->member();
        $attempt=$this->start($owner,$quiz);
        $this->actingAs($other)->get(route('member.quick-quizzes.take',$attempt))->assertForbidden();
        $this->get(route('member.quick-quizzes.result',$attempt))->assertForbidden();
        $this->post(route('member.quick-quizzes.answer',$attempt),['item_id'=>1,'answer'=>'A'])->assertForbidden();
        $this->assertDatabaseCount('tryout_attempts',0);
        $this->assertDatabaseCount('class_practice_attempts',0);
        $this->assertDatabaseCount('qq_quota_usages',1);
    }

    public function test_revision_and_archive_leave_started_attempt_pinned_to_old_version(): void
    {
        $this->quota(2);$quiz=$this->published();$user=$this->member();$attempt=$this->start($user,$quiz);
        $old=$quiz->current_version_id;
        $new=$quiz->versions()->create(['version_number'=>2,'title'=>'Revisi', 'status'=>'published',
            'seconds_per_question'=>20,'published_at'=>now()]);
        $quiz->update(['current_version_id'=>$new->id,'status'=>'archived']);
        $this->actingAs($user)->get(route('member.quick-quizzes.take',$attempt))->assertOk()->assertSee('Uji Quiz Kilat');
        $this->assertSame($old,$attempt->fresh()->quick_quiz_version_id);
        $this->get(route('member.quick-quizzes.show',$quiz))->assertNotFound();
        $this->post(route('member.quick-quizzes.start',$quiz),['start_key'=>'new-after-archive-00001'])->assertRedirect(route('member.quick-quizzes.take',$attempt));
        $this->assertDatabaseCount('qq_quota_usages',1);
    }

    public function test_finishing_thirty_questions_computes_score_and_history(): void
    {
        $this->quota(1); $quiz=$this->published(); $user=$this->member(); $attempt=$this->start($user,$quiz);
        for ($i=0;$i<30;$i++) {
            $item=$attempt->version->items()->where('sort_order',$i+1)->firstOrFail();
            $this->actingAs($user)->post(route('member.quick-quizzes.answer',$attempt),
                ['item_id'=>$item->id,'answer'=>$i<15?'A':'B'])->assertRedirect();
            $this->travel(6)->seconds();
            $this->post(route('member.quick-quizzes.next',$attempt))->assertRedirect();
        }
        $this->assertSame('completed',$attempt->fresh()->status);
        $this->assertSame(15,$attempt->fresh()->correct_count);
        $this->assertEquals(50,$attempt->fresh()->score_percent);
        $this->get(route('member.quick-quizzes.result',$attempt))->assertOk()->assertSee('50,00%')->assertSee('Pembahasan asli 30');
        $this->get(route('member.quick-quizzes.history'))->assertOk()->assertSee('Uji Quiz Kilat');
        $this->assertDatabaseCount('quick_quiz_attempt_answers',30);
        $this->assertDatabaseCount('qq_quota_usages',1);
    }
}
