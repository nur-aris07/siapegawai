<?php
namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\User;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiQuestionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
    }

    private function admin(string $role = 'operational_admin'): User
    {
        return User::factory()->create(['role'=>$role, 'status'=>'active']);
    }

    private function input(string $code = 'TWK-001'): array
    {
        return [
            'code'=>$code, 'topic_id'=>\App\Models\Topic::query()->firstOrFail()->id,
            'body'=>'Apa kedudukan dasar negara Republik Indonesia dalam sistem hukum?',
            'explanation'=>'Pancasila merupakan dasar negara sekaligus pandangan hidup bangsa Indonesia.',
            'option_a'=>'Sebagai dasar negara dan pandangan hidup bangsa',
            'option_b'=>'Sebagai acuan anggaran tahunan saja',
            'option_c'=>'Sebagai peraturan teknis kementerian',
            'option_d'=>'Sebagai peraturan daerah tertentu',
            'option_e'=>'', 'correct_option'=>'A',
        ];
    }

    public function test_admin_shell_and_question_listing_use_same_url_for_blade_and_yajra(): void
    {
        $admin = $this->admin('superadmin');
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Workspace Admin')->assertSee('Bank soal');
        $this->get('/admin/soal')->assertOk()->assertSee('Bank soal produksi')->assertSee('data-questions-table');
        $this->withHeaders(['X-Requested-With'=>'XMLHttpRequest','Accept'=>'application/json'])
            ->get('/admin/soal?draw=1&start=0&length=10')
            ->assertOk()->assertJsonStructure(['draw','recordsTotal','recordsFiltered','data']);
    }

    public function test_guest_and_participant_cannot_access_questions_admin(): void
    {
        $this->get('/admin/soal')->assertRedirect('/login');
        $participant = User::factory()->create(['role'=>'participant','status'=>'active']);
        $this->actingAs($participant)->get('/admin/soal')->assertForbidden();
        $this->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])->get('/admin/soal?draw=1')->assertForbidden();
        $this->post('/admin/soal', $this->input())->assertForbidden();
    }

    public function test_create_publish_and_audit_without_exposing_questions_to_public_demo(): void
    {
        $this->actingAs($this->admin())->post(route('questions.admin.store'), $this->input())->assertRedirect();
        $question = Question::query()->firstOrFail();
        $this->assertSame('draft', $question->status);
        $this->assertCount(4, $question->latestVersion->options);
        $this->assertNull($question->current_version_id);
        $this->post(route('questions.admin.publish', $question), ['confirmed_review'=>'1'])->assertRedirect();
        $question->refresh();
        $this->assertSame('published', $question->status);
        $this->assertNotNull($question->current_version_id);
        $this->assertSame(64, strlen($question->currentVersion->version_hash));
        $this->assertSame('published', $question->currentVersion->review_status);
        $this->assertSame(1, $question->currentVersion->options->where('is_correct',true)->count());
        $this->assertSame(1, AuditLog::query()->where('action','question.publish')->count());
        $this->get('/bank-soal')->assertOk()->assertSee('12');
    }

    public function test_edit_of_published_question_creates_new_draft_and_keeps_immutable_version(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('questions.admin.store'),$this->input());
        $question = Question::query()->firstOrFail();
        $this->post(route('questions.admin.publish',$question), ['confirmed_review'=>'1'])->assertRedirect();
        $originalVersionId = $question->fresh()->current_version_id;
        $originalBody = QuestionVersion::query()->findOrFail($originalVersionId)->body;
        $update = $this->input();
        $update['body'] = 'Perubahan baru untuk review sebelum penerbitan revisi soal.';
        $this->put(route('questions.admin.update',$question),$update)->assertRedirect();
        $question->refresh();
        $this->assertSame($originalVersionId, $question->current_version_id);
        $this->assertSame($originalBody, QuestionVersion::query()->findOrFail($originalVersionId)->body);
        $this->assertSame('draft', $question->latestVersion->review_status);
        $this->assertSame(2, $question->latestVersion->version_number);
        $this->post(route('questions.admin.publish',$question), ['confirmed_review'=>1])->assertRedirect();
        $this->assertNotEquals($originalVersionId, $question->fresh()->current_version_id);
    }

    public function test_invalid_question_cannot_be_published_and_archive_is_audited(): void
    {
        $this->actingAs($this->admin());
        $invalid = $this->input();
        $invalid['explanation'] = '';
        $this->post(route('questions.admin.store'), $invalid)->assertSessionHasErrors('explanation');
        $this->assertDatabaseCount('questions', 0);
        $this->post(route('questions.admin.store'),$this->input())->assertRedirect();
        $question = Question::query()->firstOrFail();
        $this->post(route('questions.admin.publish',$question), [])->assertSessionHasErrors('confirmed_review');
        $this->post(route('questions.admin.archive',$question),['archive_reason'=>'Konten diganti karena sudah tidak relevan'])->assertRedirect();
        $this->assertSame('archived',$question->fresh()->status);
        $this->assertDatabaseHas('audit_logs',['action'=>'question.archive','subject_id'=>$question->id]);
        $this->put(route('questions.admin.update',$question), $this->input())->assertForbidden();
    }

    public function test_soal_html_is_escaped_when_editing_and_never_executed(): void
    {
        $input = $this->input();
        $input['body'] = 'Apa makna simbol <script>alert(1)</script> pada penjelasan sistem negara?';
        $this->actingAs($this->admin())->post(route('questions.admin.store'), $input)->assertRedirect();
        $question = Question::query()->firstOrFail();
        $this->get(route('questions.admin.edit',$question))
            ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
