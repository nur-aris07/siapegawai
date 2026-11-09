<?php
namespace Tests\Feature;

use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiQuestionRevisionStateTest extends TestCase
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
            'code' => 'TWK-STATUS-01', 'topic_id' => Topic::query()->firstOrFail()->id,
            'body' => 'Dalam kerangka UUD 1945, Indonesia merupakan negara yang berdasarkan atas apa?',
            'explanation' => 'Pasal 1 ayat (3) UUD 1945 menyatakan bahwa negara Indonesia adalah negara hukum.',
            'option_a' => 'Kekuasaan tertinggi pemerintah',
            'option_b' => 'Sistem pemerintahan monarki',
            'option_c' => 'Hukum yang berlaku',
            'option_d' => 'Kekuasaan mutlak presiden',
            'option_e' => '', 'correct_option' => $correct,
        ];
    }

    public function test_published_question_can_have_unpublished_draft_revision_without_unpublishing_v1(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']));
        $this->post(route('questions.admin.store'), $this->content())->assertRedirect();
        $question = Question::query()->firstOrFail();
        $this->post(route('questions.admin.publish', $question), ['confirmed_review' => '1'])->assertRedirect();
        $publishedVersionId = $question->fresh()->current_version_id;

        $this->put(route('questions.admin.update', $question), $this->content('A'))->assertRedirect();
        $question->refresh();
        $this->assertSame('published', $question->status);
        $this->assertSame($publishedVersionId, $question->current_version_id);
        $this->assertSame('C', $question->currentVersion->options->firstWhere('is_correct', true)->label);
        $this->assertSame('draft', $question->latestVersion->review_status);
        $this->assertSame('A', $question->latestVersion->options->firstWhere('is_correct', true)->label);

        $this->get(route('questions.admin.edit', $question))
            ->assertOk()->assertSee('Terbit v1')->assertSee('Draft revisi v2')
            ->assertSee('Terbitkan revisi v2');
        $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('/admin/soal?draw=1&start=0&length=10');
        $result->assertOk()->assertJsonPath('data.0.status', 'published');
        $this->assertSame(1, (int) $result->json('data.0.active_version_number'));
        $this->assertSame(2, (int) $result->json('data.0.pending_draft_version_number'));
    }

    public function test_returning_draft_answer_to_original_does_not_publish_duplicate_version(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operational_admin', 'status' => 'active']));
        $this->post(route('questions.admin.store'), $this->content())->assertRedirect();
        $question = Question::query()->firstOrFail();
        $this->post(route('questions.admin.publish', $question), ['confirmed_review' => '1'])->assertRedirect();
        $v1 = $question->fresh()->current_version_id;

        $this->put(route('questions.admin.update', $question), $this->content('A'))->assertRedirect();
        $this->put(route('questions.admin.update', $question), $this->content('C'))->assertRedirect();
        $question->refresh();
        $this->assertSame($v1, $question->current_version_id);
        $this->assertSame('published', $question->status);
        $this->assertSame(2, $question->latestVersion->version_number);
        $this->assertSame('draft', $question->latestVersion->review_status);
        $this->assertSame('C', $question->latestVersion->options->firstWhere('is_correct', true)->label);

        $this->post(route('questions.admin.publish', $question), ['confirmed_review' => '1'])
            ->assertSessionHasErrors('publish');
        $question->refresh();
        $this->assertSame($v1, $question->current_version_id);
        $this->assertSame(1, $question->currentVersion->version_number);
        $this->assertSame('draft', $question->latestVersion->review_status);
        $this->get(route('questions.admin.edit', $question))
            ->assertOk()->assertSee('Terbit v1')->assertSee('Batalkan draft revisi v2');
    }
}
