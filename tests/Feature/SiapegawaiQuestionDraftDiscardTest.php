<?php
namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\User;
use App\Models\Topic;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiQuestionDraftDiscardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
    }

    private function content(string $correct = 'C'): array
    {
        return [
            'code' => 'TWK-DISCARD-01', 'topic_id' => Topic::query()->firstOrFail()->id,
            'body' => 'Menurut ketentuan dalam Undang-Undang Dasar 1945, negara Indonesia berdasarkan atas apa?',
            'explanation' => 'Pasal 1 ayat (3) UUD 1945 menyatakan bahwa Indonesia adalah negara hukum.',
            'option_a' => 'Kekuasaan pemerintah', 'option_b' => 'Sistem monarki',
            'option_c' => 'Hukum yang berlaku', 'option_d' => 'Kekuasaan presiden',
            'option_e' => '', 'correct_option' => $correct,
        ];
    }

    private function operator(): User
    {
        return User::factory()->create(['role' => 'operational_admin', 'status' => 'active']);
    }

    private function published(): Question
    {
        $this->post(route('questions.admin.store'), $this->content())->assertRedirect();
        $question = Question::query()->firstOrFail();
        $this->post(route('questions.admin.publish', $question), ['confirmed_review' => 1])->assertRedirect();
        return $question->fresh();
    }

    public function test_unchanged_published_content_does_not_create_a_new_draft(): void
    {
        $this->actingAs($this->operator());
        $question = $this->published();
        $this->put(route('questions.admin.update', $question), $this->content())
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame(1, $question->versions()->count());
        $this->assertSame('published', $question->fresh()->latestVersion->review_status);
        $this->assertSame(0, AuditLog::query()->where('action', 'question.draft_save')->count());
    }

    public function test_repeated_draft_saves_reuse_one_draft_and_discard_restores_v1(): void
    {
        $this->actingAs($this->operator());
        $question = $this->published();
        $active = $question->current_version_id;
        $this->put(route('questions.admin.update', $question), $this->content('A'))->assertRedirect();
        $this->put(route('questions.admin.update', $question), $this->content('A'))->assertRedirect()->assertSessionHas('status');
        $this->put(route('questions.admin.update', $question), $this->content('B'))->assertRedirect();
        $this->assertSame(2, $question->versions()->count());
        $this->assertSame(2, $question->fresh()->latestVersion->version_number);
        $this->post(route('questions.admin.discard-draft', $question), [
            'confirmed_discard' => '1', 'discard_reason' => 'Revisi sementara tidak diperlukan',
        ])->assertRedirect()->assertSessionHas('success');
        $question->refresh();
        $this->assertSame($active, $question->current_version_id);
        $this->assertSame('published', $question->status);
        $this->assertSame(1, $question->versions()->count());
        $this->assertSame(1, $question->latestVersion->version_number);
        $this->assertSame('C', $question->currentVersion->options->firstWhere('is_correct', true)->label);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'question.draft_discard', 'subject_id' => $question->id,
        ]);
        $this->get(route('questions.admin.edit', $question))
            ->assertOk()->assertSee('Tidak ada draft revisi')->assertDontSee('Batalkan draft revisi v2');
    }

    public function test_discard_requires_confirmation_reason_and_existing_draft(): void
    {
        $this->actingAs($this->operator());
        $question = $this->published();
        $this->post(route('questions.admin.discard-draft', $question), [
            'confirmed_discard' => 1, 'discard_reason' => 'Revisi tidak lagi diperlukan',
        ])->assertSessionHasErrors('discard');
        $this->put(route('questions.admin.update', $question), $this->content('A'))->assertRedirect();
        $this->post(route('questions.admin.discard-draft', $question), [])
            ->assertSessionHasErrors(['confirmed_discard', 'discard_reason']);
        $this->assertSame(2, $question->versions()->count());
    }

    public function test_participant_cannot_discard_another_users_draft(): void
    {
        $this->actingAs($this->operator());
        $question = $this->published();
        $this->put(route('questions.admin.update', $question), $this->content('A'))->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'participant', 'status' => 'active']));
        $this->post(route('questions.admin.discard-draft', $question), [
            'confirmed_discard' => '1', 'discard_reason' => 'Alasan pembatalan yang lengkap',
        ])->assertForbidden();
        $this->assertSame(2, $question->versions()->count());
    }
}
