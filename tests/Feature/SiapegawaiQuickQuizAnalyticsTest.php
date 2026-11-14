<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuickQuiz;
use App\Models\QuickQuizAttempt;
use App\Models\QuickQuizAttemptAnswer;
use App\Models\QuickQuizVersionItem;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiQuickQuizAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
    }

    private function participant(string $role = 'participant', string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    /** Two real version items and stable snapshots; no billing, quota or scoring engine writes. */
    private function versionItems(): array
    {
        $quiz = QuickQuiz::query()->create(['code' => 'QQ-ANALYTICS-FIXTURE', 'status' => 'draft']);
        $version = $quiz->versions()->create([
            'version_number' => 1, 'status' => 'published', 'title' => 'Judul Quiz Snapshot',
            'seconds_per_question' => 10, 'published_at' => now(),
        ]);
        $topic = Topic::query()->firstOrFail();
        $items = [];
        foreach ([1, 2] as $i) {
            $question = Question::query()->create([
                'topic_id' => $topic->id, 'code' => 'QQ-AN-FIXTURE-'.$i, 'status' => 'published',
            ]);
            $questionVersion = $question->versions()->create([
                'version_number' => 1, 'body' => 'Soal analisis '.$i,
                'explanation' => 'Penjelasan '.$i, 'review_status' => 'published', 'published_at' => now(),
            ]);
            $question->update(['current_version_id' => $questionVersion->id]);
            $items[] = $version->items()->create([
                'question_version_id' => $questionVersion->id, 'sort_order' => $i,
                'subject_code_snapshot' => $i === 1 ? 'TWK' : 'TIU',
                'subject_name_snapshot' => $i === 1 ? 'Wawasan Lama' : 'Intelegensia Lama',
                'topic_name_snapshot' => $i === 1 ? 'Topik Lama A' : 'Topik Lama B',
            ]);
        }
        $quiz->update(['status' => 'published', 'current_version_id' => $version->id]);
        return [$version, $items, $topic];
    }

    private function attempt(User $owner, int $versionId, string $status, int $correct, int $number): QuickQuizAttempt
    {
        return QuickQuizAttempt::query()->create([
            'user_id' => $owner->id,
            'quick_quiz_version_id' => $versionId,
            'start_key' => 'qq-analytics-fixture-'.$number.'-'. $owner->id,
            'status' => $status, 'phase' => $status === 'active' ? 'question' : 'finished',
            'current_index' => 0, 'total_questions' => 2,
            'question_started_at' => now()->subMinutes($number + 2),
            'question_expires_at' => now()->subMinutes($number + 2)->addSeconds(10),
            'last_seen_at' => now(), 'started_at' => now()->subMinutes($number + 2),
            'finished_at' => $status === 'active' ? null : now()->subMinutes(20 - $number),
            'correct_count' => $correct,
            'score_percent' => $status === 'active' ? null : 50.00 * $correct,
        ]);
    }

    private function answer(QuickQuizAttempt $attempt, QuickQuizVersionItem $item, bool $correct,
        int $ms, string $status = 'answered'): void
    {
        QuickQuizAttemptAnswer::query()->create([
            'quick_quiz_attempt_id' => $attempt->id,
            'quick_quiz_version_item_id' => $item->id,
            'answer_label' => $status === 'timeout' ? null : ($correct ? 'A' : 'B'),
            'status' => $status, 'is_correct' => $correct, 'response_ms' => $ms,
            'locked_at' => now(),
        ]);
    }

    public function test_empty_state_when_no_completed_sessions_even_if_active_or_abandoned(): void
    {
        $member = $this->participant();
        [$version, $items] = $this->versionItems();
        $active = $this->attempt($member, $version->id, 'active', 0, 1);
        $this->answer($active, $items[0], true, 500);
        $abandoned = $this->attempt($member, $version->id, 'abandoned', 1, 2);
        $this->answer($abandoned, $items[1], true, 900);

        $this->actingAs($member)->get(route('member.quick-quizzes.analytics'))
            ->assertOk()->assertSee('Belum ada sesi Quiz Kilat selesai')
            ->assertViewHas('summary', fn (array $summary) => $summary['sessions'] === 0 && $summary['locked'] === 0)
            ->assertViewHas('trend', fn ($trend) => $trend->isEmpty());
        $this->assertDatabaseCount('quick_quiz_attempt_answers', 2);
    }

    public function test_completed_analytics_totals_response_average_and_snapshot_grouping(): void
    {
        $member = $this->participant();
        $other = $this->participant();
        [$version, $items, $topic] = $this->versionItems();
        $first = $this->attempt($member, $version->id, 'completed', 1, 1);
        $this->answer($first, $items[0], true, 1000);
        $this->answer($first, $items[1], false, 3000);
        $second = $this->attempt($member, $version->id, 'completed', 1, 2);
        $this->answer($second, $items[0], true, 2000);
        $this->answer($second, $items[1], false, 10000, 'timeout');
        $active = $this->attempt($member, $version->id, 'active', 0, 3);
        $this->answer($active, $items[0], false, 100);
        $foreign = $this->attempt($other, $version->id, 'completed', 2, 4);
        $this->answer($foreign, $items[0], true, 250);
        $this->answer($foreign, $items[1], true, 250);
        $topic->update(['name' => 'Kategori master telah berganti']);

        $this->actingAs($member)->get(route('member.quick-quizzes.analytics'))
            ->assertOk()->assertSee('Analisis Quiz Kilat')->assertSee('Wawasan Lama')
            ->assertSee('Intelegensia Lama')->assertSee('Topik Lama A')
            ->assertSee('Topik Lama B')->assertDontSee('Kategori master telah berganti')
            ->assertSee('2,00 dtk')->assertSee('50,0%')
            ->assertViewHas('summary', function (array $summary) {
                return $summary['sessions'] === 2 && $summary['locked'] === 4
                    && $summary['correct'] === 2 && $summary['wrong'] === 1
                    && $summary['timeout'] === 1 && $summary['accuracy'] === 50.0
                    && (float) $summary['average_score'] === 50.0
                    && round((float) $summary['average_response_ms']) === 2000.0;
            })->assertViewHas('subjects', function ($subjects) {
                return $subjects->count() === 2
                    && $subjects->firstWhere('subject_code_snapshot', 'TWK')->correct == 2
                    && $subjects->firstWhere('subject_code_snapshot', 'TIU')->timeout == 1;
            })->assertViewHas('trend', fn ($trend) => $trend->count() === 2);
        $this->assertDatabaseCount('quick_quiz_attempt_answers', 7);
    }

    public function test_route_is_private_and_does_not_leak_other_participants_or_html(): void
    {
        $this->get(route('member.quick-quizzes.analytics'))->assertRedirect(route('login'));
        [$version, $items] = $this->versionItems();
        $owner = $this->participant();
        $private = $this->attempt($owner, $version->id, 'completed', 1, 1);
        $items[0]->update(['topic_name_snapshot' => '<script>alert(9)</script>']);
        $this->answer($private, $items[0], true, 800);
        $this->actingAs($owner)->get(route('member.quick-quizzes.analytics'))
            ->assertOk()->assertSee('&lt;script&gt;alert(9)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(9)</script>', false);
        $this->actingAs($this->participant())->get(route('member.quick-quizzes.analytics'))
            ->assertOk()->assertSee('Belum ada sesi Quiz Kilat selesai')
            ->assertDontSee('alert(9)');
        $this->actingAs($this->participant('operational_admin'))
            ->get(route('member.quick-quizzes.analytics'))->assertForbidden();
        $this->actingAs($this->participant('participant', 'suspended'))
            ->get(route('member.quick-quizzes.analytics'))->assertForbidden();
    }

    public function test_last_eight_sessions_are_oldest_to_newest_and_links_exist(): void
    {
        $member = $this->participant();
        [$version] = $this->versionItems();
        $ids = [];
        for ($i = 1; $i <= 10; $i++) {
            $ids[] = $this->attempt($member, $version->id, 'completed', 1, $i)->id;
        }
        $this->actingAs($member)->get(route('member.quick-quizzes.analytics'))
            ->assertOk()->assertSee('Tren 8 sesi selesai terakhir')
            ->assertViewHas('trend', fn ($trend) => $trend->count() === 8
                && (int) $trend->first()->id === $ids[2]
                && (int) $trend->last()->id === $ids[9]);
        $this->get(route('member.quick-quizzes.history'))->assertOk()->assertSee('Analisis Quiz Kilat')->assertSee('qq-analytics-entry-links');
        $this->get(route('member.quick-quizzes.index'))->assertOk()->assertSee('Analisis Quiz Kilat')->assertSee('qq-analytics-entry-links');
    }

    public function test_dashboard_visualization_tables_are_collapsible_and_js_is_a_vite_entry(): void
    {
        $member = $this->participant();
        [$version, $items] = $this->versionItems();
        $attempt = $this->attempt($member, $version->id, 'completed', 1, 1);
        $this->answer($attempt, $items[0], true, 500);
        $this->answer($attempt, $items[1], false, 700);

        $response = $this->actingAs($member)->get(route('member.quick-quizzes.analytics'));
        $response->assertOk()
            ->assertSee('Distribusi jawaban terkunci')
            ->assertSee('Tren 8 sesi selesai terakhir')
            ->assertSee('Ketepatan menurut kategori')
            ->assertSee('Rincian kategori &amp; topik', false)
            ->assertSee('data-qq-analytics-grid', false)
            ->assertSee('data-qq-analytics-table', false)
            ->assertSee('data-qq-page-size', false)
            ->assertSee('data-qq-search', false)
            ->assertSee('Menurut topik saat terbit');

        $vite = file_get_contents(base_path('vite.config.js'));
        $this->assertIsString($vite);
        $this->assertStringContainsString("'resources/js/member-qq-analytics.js'", $vite);
    }

}
