<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\TryoutAttempt;
use App\Models\TryoutAttemptAnswer;
use App\Models\TryoutPackage;
use App\Models\TryoutPackageItem;
use App\Models\User;
use App\Services\MemberSubjectAnalysisService;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiMemberSubjectAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
    }

    private function participant(): User
    {
        return User::factory()->create(['role' => 'participant', 'status' => 'active']);
    }

    private function item(TryoutPackage $package, string $subjectCode, int $position, string $code): array
    {
        $subject = Subject::query()->where('code', $subjectCode)->firstOrFail();
        $topic = Topic::query()->where('subject_id', $subject->id)->firstOrFail();
        $question = Question::query()->create(['code' => $code, 'topic_id' => $topic->id, 'status' => 'published']);
        $v1 = QuestionVersion::query()->create([
            'question_id' => $question->id,
            'version_number' => 1,
            'body' => 'Pertanyaan ' . $code,
            'explanation' => 'Jawaban yang benar: B',
            'review_status' => 'published',
            'published_at' => now(),
        ]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            QuestionOption::query()->create([
                'question_version_id' => $v1->id,
                'label' => $label,
                'body' => 'Opsi ' . $label,
                'sort_order' => $i + 1,
                'is_correct' => $label === 'B',
            ]);
        }
        $question->update(['current_version_id' => $v1->id]);
        $item = TryoutPackageItem::query()->create([
            'tryout_package_id' => $package->id,
            'question_version_id' => $v1->id,
            'sort_order' => $position,
        ]);
        return [$question, $v1, $item];
    }

    private function package(string $status = 'published'): TryoutPackage
    {
        return TryoutPackage::query()->create([
            'code' => 'TRY-ANALYSIS-01',
            'name' => 'Uji Analisis Materi',
            'description' => 'Paket pengujian per materi',
            'status' => $status,
            'duration_minutes' => 30,
            'max_attempts' => 10,
            'published_at' => now(),
        ]);
    }

    private function attempt(User $user, TryoutPackage $package, int $number, string $status = 'completed'): TryoutAttempt
    {
        return TryoutAttempt::query()->create([
            'user_id' => $user->id,
            'tryout_package_id' => $package->id,
            'attempt_number' => $number,
            'status' => $status,
            'started_at' => now()->subMinutes(10),
            'expires_at' => now()->addMinutes(20),
            'finished_at' => $status === 'in_progress' ? null : now(),
            'total_questions' => 2,
            'answered_count' => $status === 'in_progress' ? null : 1,
            'correct_count' => $status === 'in_progress' ? null : 1,
            'score_percent' => $status === 'in_progress' ? null : 50,
        ]);
    }

    private function answer(TryoutAttempt $attempt, TryoutPackageItem $item, string $label): void
    {
        TryoutAttemptAnswer::query()->create([
            'tryout_attempt_id' => $attempt->id,
            'tryout_package_item_id' => $item->id,
            'answer_label' => $label,
            'flagged' => false,
        ]);
    }

    public function test_subject_counts_use_pinned_version_and_include_unanswered_questions(): void
    {
        $package = $this->package();
        [$twkQuestion, $v1, $twkItem] = $this->item($package, 'TWK', 1, 'TWK-ANALYSIS');
        [, , $tiuItem] = $this->item($package, 'TIU', 2, 'TIU-ANALYSIS');
        $owner = $this->participant();
        $attempt = $this->attempt($owner, $package, 1);
        $this->answer($attempt, $twkItem, 'B'); // Correct for pinned v1.
        // TIU is deliberately unanswered.

        // Current bank question later changes to A, but v1 remains B.
        $v2 = QuestionVersion::query()->create([
            'question_id' => $twkQuestion->id,
            'version_number' => 2,
            'body' => 'Pertanyaan revisi',
            'review_status' => 'published',
            'published_at' => now(),
        ]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            QuestionOption::query()->create([
                'question_version_id' => $v2->id,
                'label' => $label,
                'body' => 'Opsi revisi ' . $label,
                'sort_order' => $i + 1,
                'is_correct' => $label === 'A',
            ]);
        }
        $twkQuestion->update(['current_version_id' => $v2->id]);

        $rows = app(MemberSubjectAnalysisService::class)->forAttempt($attempt)->keyBy('code');
        $this->assertSame(1, $rows['TWK']->total);
        $this->assertSame(1, $rows['TWK']->correct);
        $this->assertSame(0, $rows['TWK']->wrong);
        $this->assertSame(100.0, $rows['TWK']->accuracy);
        $this->assertSame(1, $rows['TIU']->total);
        $this->assertSame(1, $rows['TIU']->unanswered);
        $this->assertNull($rows['TIU']->accuracy);
    }

    public function test_history_aggregates_only_owned_finished_attempts_and_counts_repeated_questions(): void
    {
        $package = $this->package('archived');
        [, , $twk] = $this->item($package, 'TWK', 1, 'TWK-ANALYSIS-B');
        [, , $tiu] = $this->item($package, 'TIU', 2, 'TIU-ANALYSIS-B');
        $owner = $this->participant();
        $other = $this->participant();

        $first = $this->attempt($owner, $package, 1);
        $this->answer($first, $twk, 'B');
        $this->answer($first, $tiu, 'A');
        $second = $this->attempt($owner, $package, 2, 'timed_out');
        $this->answer($second, $twk, 'A');
        $inProgress = $this->attempt($owner, $package, 3, 'in_progress');
        $this->answer($inProgress, $twk, 'B');
        $stranger = $this->attempt($other, $package, 1);
        $this->answer($stranger, $twk, 'B');

        $rows = app(MemberSubjectAnalysisService::class)->forUser($owner->id)->keyBy('code');
        $this->assertSame(2, $rows['TWK']->total);
        $this->assertSame(2, $rows['TWK']->answered);
        $this->assertSame(1, $rows['TWK']->correct);
        $this->assertSame(1, $rows['TWK']->wrong);
        $this->assertSame(50.0, $rows['TWK']->accuracy);
        $this->assertSame(2, $rows['TIU']->total);
        $this->assertSame(1, $rows['TIU']->wrong);
        $this->assertSame(1, $rows['TIU']->unanswered);
        $this->assertSame(0, $rows['TIU']->correct);
    }

    public function test_member_result_and_history_render_subject_breakdown_but_other_user_is_forbidden(): void
    {
        $package = $this->package();
        [, , $item] = $this->item($package, 'TWK', 1, 'TWK-ANALYSIS-C');
        $owner = $this->participant();
        $attempt = $this->attempt($owner, $package, 1);
        $this->answer($attempt, $item, 'B');

        $this->actingAs($owner)
            ->get(route('member.tryouts.result', $attempt))
            ->assertOk()
            ->assertSee('Hasil per materi pada percobaan ini')
            ->assertSee('TWK')
            ->assertSee('Ketepatan dari 1 dijawab');
        $this->get(route('member.tryouts.history'))
            ->assertOk()
            ->assertSee('Analisis hasil per materi')
            ->assertSee('TWK');

        $this->actingAs($this->participant())
            ->get(route('member.tryouts.result', $attempt))
            ->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']))
            ->get(route('member.tryouts.history'))
            ->assertForbidden();
    }

    public function test_admin_copy_reports_cat_enabled_without_pretending_admin_can_enter_member_cat(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('hasil tersimpan di MySQL')
            ->assertDontSee('Engine CAT peserta belum aktif');
    }
}
