<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\ClassPractice;
use App\Models\ClassPracticeAttempt;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialProgress;
use App\Models\Question;
use App\Models\QuickQuiz;
use App\Models\QuickQuizAttempt;
use App\Models\QuickQuizAttemptAnswer;
use App\Models\Topic;
use App\Models\TryoutAttempt;
use App\Models\TryoutPackage;
use App\Models\User;
use App\Services\QuickQuizAnalyticsService;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiMemberDomainAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'participant', string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function tryoutAttempt(User $user, TryoutPackage $package, int $number, string $status, int $score): TryoutAttempt
    {
        return TryoutAttempt::query()->create([
            'user_id' => $user->id, 'tryout_package_id' => $package->id,
            'attempt_number' => $number, 'status' => $status,
            'started_at' => now()->subHour(), 'expires_at' => now()->addHour(),
            'finished_at' => $status === 'in_progress' ? null : now()->subMinutes(100 - $number),
            'total_questions' => 10, 'answered_count' => $status === 'in_progress' ? null : 10,
            'correct_count' => $status === 'in_progress' ? null : (int) ($score / 10),
            'score_percent' => $status === 'in_progress' ? null : $score,
        ]);
    }

    private function makeClass(User $admin, string $title, string $status = 'open'): LearningClass
    {
        return LearningClass::query()->create([
            'slug' => 'anl-'.fake()->unique()->slug(2), 'title' => $title,
            'description' => 'Kelas untuk fixture analisis domain.',
            'status' => $status, 'created_by' => $admin->id,
        ]);
    }

    private function quizFixture(User $member, User $other): void
    {
        $this->seed(SiapegawaiSubjectsSeeder::class);
        $quiz = QuickQuiz::query()->create(['code' => 'ANL-DOMAIN-QQ', 'status' => 'draft']);
        $version = $quiz->versions()->create([
            'version_number' => 1, 'status' => 'published', 'title' => 'Analisis QQ fixture',
            'seconds_per_question' => 10, 'published_at' => now(),
        ]);
        $question = Question::query()->create([
            'topic_id' => Topic::query()->firstOrFail()->id,
            'code' => 'ANL-DOMAIN-QUESTION', 'status' => 'published',
        ]);
        $questionVersion = $question->versions()->create([
            'version_number' => 1, 'body' => 'Contoh perhitungan analisis',
            'explanation' => 'Pembahasan pengujian', 'review_status' => 'published', 'published_at' => now(),
        ]);
        $question->update(['current_version_id' => $questionVersion->id]);
        $item = $version->items()->create([
            'question_version_id' => $questionVersion->id, 'sort_order' => 1,
            'subject_code_snapshot' => 'TWK', 'subject_name_snapshot' => 'Wawasan saat terbit',
            'topic_name_snapshot' => 'Topik saat terbit',
        ]);
        $quiz->update(['status' => 'published', 'current_version_id' => $version->id]);

        foreach ([[$member, 'completed', true, 1250], [$member, 'abandoned', true, 100], [$other, 'completed', false, 950]] as $i => $fixture) {
            [$owner, $status, $correct, $response] = $fixture;
            $attempt = QuickQuizAttempt::query()->create([
                'user_id' => $owner->id, 'quick_quiz_version_id' => $version->id,
                'start_key' => 'domain-qq-'.$i, 'status' => $status, 'phase' => 'finished',
                'current_index' => 0, 'total_questions' => 1,
                'question_started_at' => now()->subMinutes(3),
                'question_expires_at' => now()->subMinutes(3)->addSeconds(10),
                'last_seen_at' => now(), 'started_at' => now()->subMinutes(3),
                'finished_at' => now()->subMinutes(1),
                'correct_count' => $correct ? 1 : 0, 'score_percent' => $correct ? 100 : 0,
            ]);
            QuickQuizAttemptAnswer::query()->create([
                'quick_quiz_attempt_id' => $attempt->id,
                'quick_quiz_version_item_id' => $item->id, 'answer_label' => $correct ? 'A' : 'B',
                'status' => 'answered', 'is_correct' => $correct, 'response_ms' => $response,
                'locked_at' => now(),
            ]);
        }
    }

    public function test_hub_requires_active_participant_and_keeps_private_cache_disabled(): void
    {
        $url = route('member.analytics.index');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user('operational_admin'))->get($url)->assertForbidden();
        $this->actingAs($this->user('superadmin'))->get($url)->assertForbidden();
        $this->actingAs($this->user('participant', 'suspended'))->get($url)->assertForbidden();
        $this->actingAs($this->user('participant', 'pending'))->get($url)->assertForbidden();
        $response = $this->actingAs($this->user())->get($url)->assertOk()
            ->assertSee('Analisis Kemampuan')->assertSee('CAT / Tryout')
            ->assertSee('Latihan Kelas')->assertSee('Quiz Kilat');

        // HTTP Cache-Control directives have no significant ordering.
        // Assert both directives independently rather than comparing raw header text.
        $directives = array_map(
            static fn (string $part): string => strtolower(trim($part)),
            explode(',', (string) $response->headers->get('Cache-Control'))
        );
        $this->assertContains('private', $directives);
        $this->assertContains('no-store', $directives);
    }

    public function test_empty_hub_does_not_turn_active_tryout_into_final_result(): void
    {
        $member = $this->user();
        $package = TryoutPackage::query()->create([
            'code' => 'ANL-D-EMPTY', 'name' => 'Tryout aktif',
            'status' => 'published', 'duration_minutes' => 30, 'max_attempts' => 9,
        ]);
        $this->tryoutAttempt($member, $package, 1, 'in_progress', 100);
        $this->actingAs($member)->get(route('member.analytics.index'))
            ->assertOk()->assertSee('Belum ada hasil Tryout final')
            ->assertSee('Belum ada kelas dengan akses aktif')
            ->assertSee('Belum ada sesi Quiz Kilat selesai')
            ->assertViewHas('cat', fn (array $cat) => $cat['sessions'] === 0 && $cat['average_score'] === null && $cat['points'] === [])
            ->assertViewHas('classes', fn (array $classes) => $classes['count'] === 0)
            ->assertViewHas('qq', fn (array $qq) => $qq['sessions'] === 0 && $qq['locked'] === 0);
        $this->assertDatabaseHas('tryout_attempts', ['user_id' => $member->id, 'status' => 'in_progress']);
    }

    public function test_cat_metrics_trend_are_final_only_last_five_and_owner_scoped(): void
    {
        $member = $this->user();
        $other = $this->user();
        $package = TryoutPackage::query()->create([
            'code' => 'ANL-D-CAT', 'name' => 'Tryout deskriptif',
            'status' => 'archived', 'duration_minutes' => 20, 'max_attempts' => 30,
        ]);
        for ($i = 1; $i <= 6; $i++) {
            $this->tryoutAttempt($member, $package, $i, $i === 4 ? 'timed_out' : 'completed', 10 * $i);
        }
        $this->tryoutAttempt($member, $package, 7, 'in_progress', 100);
        $this->tryoutAttempt($other, $package, 1, 'completed', 100);
        $this->actingAs($member)->get(route('member.analytics.index'))
            ->assertOk()->assertSee('35,0%')->assertSee('60,0%')
            ->assertViewHas('cat', function (array $cat): bool {
                return $cat['sessions'] === 6
                    && abs($cat['average_score'] - 35.0) < 0.001
                    && abs($cat['best_score'] - 60.0) < 0.001
                    && count($cat['points']) === 5
                    && $cat['points'][0]['score'] === 20.0
                    && $cat['points'][4]['score'] === 60.0
                    && count(explode(' ', $cat['polyline'])) === 5;
            });
        $this->actingAs($other)->get(route('member.analytics.index'))
            ->assertOk()->assertViewHas('cat', fn (array $cat) => $cat['sessions'] === 1 && $cat['best_score'] === 100.0);
    }

    public function test_class_progress_practice_and_revoke_are_kept_separate_and_private(): void
    {
        $admin = $this->user('superadmin');
        $member = $this->user();
        $other = $this->user();
        $ownClass = $this->makeClass($admin, 'Kelas Terdaftar Analisis');
        $foreign = $this->makeClass($admin, 'Kelas Rahasia Dicabut');
        $enrollment = ClassEnrollment::query()->create([
            'class_id' => $ownClass->id, 'user_id' => $member->id,
            'status' => 'active', 'approved_by' => $admin->id, 'approved_at' => now(),
        ]);
        ClassEnrollment::query()->create([
            'class_id' => $foreign->id, 'user_id' => $member->id,
            'status' => 'revoked', 'approved_by' => $admin->id, 'approved_at' => now(),
        ]);
        $published = LearningMaterial::query()->create([
            'class_id' => $ownClass->id, 'title' => 'Materi Terbit',
            'content' => 'Materi fixture', 'status' => 'published', 'sort_order' => 1,
            'published_at' => now(),
        ]);
        LearningMaterial::query()->create([
            'class_id' => $ownClass->id, 'title' => 'Materi Draft',
            'content' => 'Draft fixture', 'status' => 'draft', 'sort_order' => 2,
        ]);
        LearningMaterialProgress::query()->create([
            'user_id' => $member->id, 'material_id' => $published->id,
            'completed_version_number' => 1, 'completed_at' => now(),
        ]);
        $practice = ClassPractice::query()->create([
            'class_id' => $ownClass->id, 'title' => 'Latihan final aktif',
            'status' => 'published', 'duration_minutes' => 30, 'max_attempts' => 3,
        ]);
        $revokedPractice = ClassPractice::query()->create([
            'class_id' => $foreign->id, 'title' => 'Latihan dicabut',
            'status' => 'published', 'duration_minutes' => 30, 'max_attempts' => 3,
        ]);
        foreach ([[$practice, 'completed', $member], [$practice, 'in_progress', $member], [$revokedPractice, 'completed', $member], [$practice, 'completed', $other]] as $i => $row) {
            [$item, $status, $owner] = $row;
            ClassPracticeAttempt::query()->create([
                'class_practice_id' => $item->id, 'user_id' => $owner->id,
                'attempt_number' => $i + 1, 'status' => $status,
                'started_at' => now()->subHour(), 'expires_at' => now()->addHour(),
                'finished_at' => $status === 'completed' ? now() : null,
                'total_questions' => 2, 'answered_count' => 1,
                'correct_count' => 1, 'score_percent' => 50,
            ]);
        }
        $this->actingAs($member)->get(route('member.analytics.index'))
            ->assertOk()->assertSee('Kelas Terdaftar Analisis')
            ->assertDontSee('Kelas Rahasia Dicabut')
            ->assertViewHas('classes', function (array $classes): bool {
                return $classes['count'] === 1 && $classes['practice_sessions'] === 1
                    && count($classes['previews']) === 1
                    && $classes['previews'][0]['total'] === 1
                    && $classes['previews'][0]['completed'] === 1;
            });
        $enrollment->update(['status' => 'revoked']);
        $this->get(route('member.analytics.index'))
            ->assertOk()->assertDontSee('Kelas Terdaftar Analisis')
            ->assertViewHas('classes', fn (array $classes) => $classes['count'] === 0 && $classes['practice_sessions'] === 0);
    }

    public function test_qq_data_matches_existing_service_and_does_not_include_abandoned_or_other_users(): void
    {
        $member = $this->user();
        $other = $this->user();
        $this->quizFixture($member, $other);
        $expected = app(QuickQuizAnalyticsService::class)->forUser((int) $member->id)['summary'];
        $this->actingAs($member)->get(route('member.analytics.index'))
            ->assertOk()->assertSee('100,0%')->assertSee('1,25 dtk')
            ->assertViewHas('qq', function (array $qq) use ($expected): bool {
                foreach ($expected as $key => $value) {
                    if ($qq[$key] !== $value) return false;
                }
                return $qq['sessions'] === 1 && $qq['locked'] === 1
                    && $qq['correct'] === 1 && $qq['bar']['correct'] === 400.0;
            });
    }

    public function test_navigation_has_one_analytics_hub_entry_and_domain_details_remain(): void
    {
        $member = $this->user();
        $this->actingAs($member)->get(route('dashboard'))
            ->assertOk()->assertSee(route('member.analytics.index'), false)
            ->assertDontSee('>Riwayat &amp; progres</a>', false);
        $this->get(route('member.analytics.index'))->assertOk()
            ->assertSee(route('member.tryouts.history'), false)
            ->assertSee(route('member.classes.index'), false)
            ->assertSee(route('member.quick-quizzes.analytics'), false);
        $this->get(route('member.tryouts.index'))->assertOk()
            ->assertSee(route('member.tryouts.history'), false);
        $this->get(route('member.tryouts.history'))->assertOk()
            ->assertSee(route('member.analytics.index'), false);
        $this->get(route('member.classes.index'))->assertOk()
            ->assertSee(route('member.analytics.index'), false);
        $this->get(route('member.quick-quizzes.analytics'))->assertOk()
            ->assertSee(route('member.analytics.index'), false);
        $this->assertSame(1, substr_count($this->get(route('dashboard'))->getContent(), '>Analisis kemampuan</a>'));
    }
}
