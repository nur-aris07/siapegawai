<?php
namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\Question;
use App\Models\QuestionVersionMedia;
use App\Models\QuickQuiz;
use App\Models\QuickQuizQuotaPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SiapegawaiFigP3FixtureCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_testing_database_requires_explicit_bootstrap_and_dry_run_does_not_write(): void
    {
        $this->artisan('siapegawai:fig-p3-demo', ['--dry-run'=>true])->assertExitCode(1);
        $this->artisan('siapegawai:fig-p3-demo', [
            '--dry-run'=>true, '--bootstrap-users'=>true, '--qa-quota'=>true,
        ])->assertExitCode(0);
        $this->assertDatabaseMissing('users', ['email'=>'figp3-qa-admin@example.test']);
        $this->assertSame(0, Question::query()->where('code','like','FIGP3-QA-Q%')->count());
        $this->assertSame(0, QuickQuizQuotaPolicy::query()->count());
    }

    public function test_bootstrap_creates_usable_qa_dataset_without_mixing_other_databases_or_paid_grants(): void
    {
        Storage::fake('question_media_private');
        $args=['--apply'=>true, '--bootstrap-users'=>true, '--qa-quota'=>true];
        $this->artisan('siapegawai:fig-p3-demo', $args)->assertExitCode(0);
        $this->artisan('siapegawai:fig-p3-demo', ['--verify'=>true])->assertExitCode(0);
        // Idempotent rerun must never create extra questions or reset a user password.
        $this->artisan('siapegawai:fig-p3-demo', $args)->assertExitCode(0);
        $admin=User::query()->where('email','figp3-qa-admin@example.test')->firstOrFail();
        $participant=User::query()->where('email','figp3-qa-participant@example.test')->firstOrFail();
        $this->assertSame('superadmin', $admin->role);
        $this->assertSame('active', $participant->status);
        $this->assertSame(30, Question::query()->where('code','like','FIGP3-QA-Q%')->count());
        $this->assertSame(8, QuestionVersionMedia::query()->whereHas('version.question',
            fn ($q)=>$q->where('code','like','FIGP3-QA-Q%'))->count());
        $this->assertSame(30, QuickQuiz::query()->where('code','FIGP3-QA-QQ-01')
            ->firstOrFail()->currentVersion->items()->count());
        $this->assertTrue(ClassEnrollment::query()->where('user_id',$participant->id)
            ->where('status','active')->exists());
        $this->assertTrue(QuickQuizQuotaPolicy::query()->where('code','FIGP3-QA-PEJUANG')
            ->where('status','active')->exists());
        $this->assertDatabaseMissing('access_grants', ['user_id'=>$participant->id]);
    }
}
