<?php
namespace Tests\Feature;

use App\Models\Question;
use App\Models\Topic;
use App\Models\TryoutAttempt;
use App\Models\TryoutPackage;
use App\Models\User;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiMemberTryoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
    }

    private function user(string $role='participant',string $status='active'): User
    {
        return User::factory()->create(['role'=>$role,'status'=>$status]);
    }

    private function publishPackage(int $maxAttempts=1, int $duration=5): TryoutPackage
    {
        $admin=$this->user('superadmin');
        $this->actingAs($admin);
        $this->post(route('questions.admin.store'),[
            'code'=>'TWK-908','topic_id'=>Topic::query()->firstOrFail()->id,
            'body'=>'Apa prinsip utama dalam kehidupan bernegara di Indonesia?',
            'explanation'=>'Persatuan dan penghormatan terhadap hukum harus dijaga.',
            'option_a'=>'Mengutamakan kekuasaan pribadi',
            'option_b'=>'Menjaga persatuan dan hukum',
            'option_c'=>'Menolak semua aturan',
            'option_d'=>'Menutup akses pendidikan',
            'option_e'=>'','correct_option'=>'B',
        ])->assertRedirect();
        $question=Question::query()->firstOrFail();
        $this->post(route('questions.admin.publish',$question),['confirmed_review'=>1])->assertRedirect();
        $this->post(route('tryouts.admin.store'),[
            'code'=>'TRYOUT-908','name'=>'Latihan anggota awal','description'=>'Soal untuk uji integrasi',
            'duration_minutes'=>$duration,'max_attempts'=>$maxAttempts,
            'version_ids'=>[$question->fresh()->current_version_id],
        ])->assertRedirect();
        $package=TryoutPackage::query()->firstOrFail();
        $this->post(route('tryouts.admin.publish',$package),['confirmed_review'=>1])->assertRedirect();
        return $package->fresh();
    }

    public function test_participant_permissions_and_unpublished_results_are_not_exposed(): void
    {
        $package=$this->publishPackage();
        auth()->logout();
        $this->get(route('member.tryouts.index'))->assertRedirect('/login');
        $this->actingAs($this->user('superadmin'))->get(route('member.tryouts.index'))->assertForbidden();
        $this->actingAs($this->user('participant','pending'))->get(route('member.tryouts.index'))->assertForbidden();
        $member=$this->user();
        $this->actingAs($member)->post(route('member.tryouts.start',$package))->assertRedirect();
        $attempt=TryoutAttempt::query()->where('user_id',$member->id)->firstOrFail();
        $this->get(route('member.tryouts.result',$attempt))->assertRedirect(route('member.tryouts.take',$attempt));
        $this->get(route('member.tryouts.take',$attempt))->assertOk()->assertSee('Apa prinsip utama')->assertDontSee('Kunci:')->assertDontSee('Persatuan dan penghormatan terhadap hukum harus dijaga.');
        $other=$this->user();
        $this->actingAs($other)->get(route('member.tryouts.take',$attempt))->assertForbidden();
        $this->get(route('member.tryouts.result',$attempt))->assertForbidden();
        $this->patchJson(route('member.tryouts.save',$attempt),['item_id'=>123,'answer'=>'A','flagged'=>false])->assertForbidden();
    }

    public function test_autosave_reload_and_submit_scored_only_by_server(): void
    {
        $package=$this->publishPackage();
        $member=$this->user();
        $this->actingAs($member)->post(route('member.tryouts.start',$package))->assertRedirect();
        $attempt=TryoutAttempt::query()->where('user_id',$member->id)->firstOrFail();
        $item=$package->items()->firstOrFail();
        $this->patchJson(route('member.tryouts.save',$attempt),[
            'item_id'=>$item->id,'answer'=>'B','flagged'=>true,
        ])->assertOk()->assertJsonPath('saved',true)->assertJsonPath('answered',1);
        $this->get(route('member.tryouts.take',$attempt))->assertOk()->assertSee('Apa prinsip utama');
        $this->post(route('member.tryouts.submit',$attempt))->assertRedirect(route('member.tryouts.result',$attempt));
        $this->assertDatabaseHas('tryout_attempts',[
            'id'=>$attempt->id,'status'=>'completed','answered_count'=>1,'correct_count'=>1,
        ]);
        $this->get(route('member.tryouts.result',$attempt))->assertOk()->assertSee('Pembahasan tiap soal')->assertSee('Kunci:');
        $this->patchJson(route('member.tryouts.save',$attempt),['item_id'=>$item->id,'answer'=>'A','flagged'=>false])->assertStatus(409);
        $this->assertDatabaseHas('tryout_attempt_answers',['tryout_attempt_id'=>$attempt->id,'answer_label'=>'B']);
    }

    public function test_deadline_is_enforced_and_max_attempts_cannot_be_bypassed(): void
    {
        $package=$this->publishPackage(1,5);
        $member=$this->user();
        $this->actingAs($member)->post(route('member.tryouts.start',$package))->assertRedirect();
        $attempt=TryoutAttempt::query()->where('user_id',$member->id)->firstOrFail();
        $item=$package->items()->firstOrFail();
        $this->travel(6)->minutes();
        $this->patchJson(route('member.tryouts.save',$attempt),['item_id'=>$item->id,'answer'=>'B','flagged'=>false])->assertStatus(409);
        $this->assertDatabaseHas('tryout_attempts',['id'=>$attempt->id,'status'=>'timed_out','correct_count'=>0]);
        $this->post(route('member.tryouts.start',$package))->assertSessionHasErrors('tryout');
        $this->assertDatabaseCount('tryout_attempts',1);
        $this->travelBack();
    }

    public function test_scoring_uses_pinned_version_even_after_question_key_changes(): void
    {
        $package=$this->publishPackage();
        $item=$package->items()->firstOrFail();
        $originalVersionId=$item->question_version_id;
        $q=Question::query()->firstOrFail();
        $this->put(route('questions.admin.update',$q),[
            'code'=>'TWK-908','topic_id'=>$q->topic_id,
            'body'=>'Apa prinsip utama dalam kehidupan bernegara di Indonesia?',
            'explanation'=>'Penjelasan revisi yang berbeda dan diterbitkan.',
            'option_a'=>'Mengutamakan kekuasaan pribadi',
            'option_b'=>'Menjaga persatuan dan hukum',
            'option_c'=>'Menolak semua aturan',
            'option_d'=>'Menutup akses pendidikan',
            'option_e'=>'','correct_option'=>'A',
        ])->assertRedirect();
        $this->post(route('questions.admin.publish',$q),['confirmed_review'=>1])->assertRedirect();
        $this->assertNotEquals($originalVersionId,$q->fresh()->current_version_id);
        $this->assertSame((int)$originalVersionId,(int)$package->items()->firstOrFail()->question_version_id);
        $member=$this->user();
        $this->actingAs($member)->post(route('member.tryouts.start',$package))->assertRedirect();
        $attempt=TryoutAttempt::query()->where('user_id',$member->id)->firstOrFail();
        $this->patchJson(route('member.tryouts.save',$attempt),['item_id'=>$item->id,'answer'=>'B','flagged'=>false])->assertOk();
        $this->post(route('member.tryouts.submit',$attempt))->assertRedirect();
        $this->assertSame(1,(int)$attempt->fresh()->correct_count);
    }

    public function test_start_is_idempotent_and_rejects_invalid_item_or_choice(): void
    {
        $package=$this->publishPackage(2);
        $member=$this->user();
        $this->actingAs($member);
        $this->post(route('member.tryouts.start',$package))->assertRedirect();
        $this->post(route('member.tryouts.start',$package))->assertRedirect();
        $this->assertDatabaseCount('tryout_attempts',1);
        $attempt=TryoutAttempt::query()->firstOrFail();
        $item=$package->items()->firstOrFail();
        $this->patchJson(route('member.tryouts.save',$attempt),['item_id'=>$item->id+900,'answer'=>'B','flagged'=>false])->assertUnprocessable();
        $this->patchJson(route('member.tryouts.save',$attempt),['item_id'=>$item->id,'answer'=>'E','flagged'=>false])->assertUnprocessable();
        $this->assertDatabaseCount('tryout_attempt_answers',0);
    }
}
