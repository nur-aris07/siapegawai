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
use App\Services\TryoutEditorialService;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiHistoricalSnapshotAndCatResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
    }

    private function fixture(): array
    {
        $admin=User::factory()->create(['role'=>'superadmin','status'=>'active']);
        $member=User::factory()->create(['role'=>'participant','status'=>'active']);
        $twk=Subject::query()->where('code','TWK')->firstOrFail();
        $tiu=Subject::query()->where('code','TIU')->firstOrFail();
        $topic=Topic::query()->where('subject_id',$twk->id)->firstOrFail();
        $otherTopic=Topic::query()->where('subject_id',$tiu->id)->firstOrFail();
        $question=Question::query()->create(['code'=>'TWK-SNAPSHOT-001','topic_id'=>$topic->id,'status'=>'published']);
        $version=QuestionVersion::query()->create([
            'question_id'=>$question->id,'version_number'=>1,'body'=>'Manakah jawaban yang benar?',
            'explanation'=>'Jawaban B benar','review_status'=>'published','published_at'=>now(),
        ]);
        foreach (['A','B','C','D'] as $i=>$label) {
            QuestionOption::query()->create([
                'question_version_id'=>$version->id,'label'=>$label,'body'=>'Pilihan '.$label,
                'sort_order'=>$i+1,'is_correct'=>$label==='B',
            ]);
        }
        $question->update(['current_version_id'=>$version->id]);
        $package=TryoutPackage::query()->create([
            'code'=>'TRY-SNAPSHOT-01','name'=>'Uji snapshot','description'=>'Test',
            'duration_minutes'=>10,'max_attempts'=>2,'status'=>'draft','created_by'=>$admin->id,
        ]);
        $item=TryoutPackageItem::query()->create([
            'tryout_package_id'=>$package->id,'question_version_id'=>$version->id,'sort_order'=>1,
        ]);
        app(TryoutEditorialService::class)->publish($package,$admin);
        return compact('admin','member','twk','tiu','topic','otherTopic','question','version','package','item');
    }

    private function attempt(User $member, TryoutPackage $package): TryoutAttempt
    {
        return TryoutAttempt::query()->create([
            'user_id'=>$member->id,'tryout_package_id'=>$package->id,'attempt_number'=>1,
            'status'=>'in_progress','started_at'=>now()->subMinute(),
            'expires_at'=>now()->addMinutes(9),'total_questions'=>1,
        ]);
    }

    public function test_published_template_freezes_category_and_topic_even_when_master_is_moved(): void
    {
        $f=$this->fixture();
        $item=$f['item']->fresh();
        $this->assertSame('TWK',$item->subject_code_snapshot);
        $this->assertSame($f['twk']->name,$item->subject_name_snapshot);
        $this->assertSame((int)$f['topic']->id,(int)$item->topic_id_snapshot);
        $this->assertSame($f['topic']->name,$item->topic_name_snapshot);
        $originalSubjectName=$item->subject_name_snapshot;
        $f['question']->update(['topic_id'=>$f['otherTopic']->id]);
        $f['twk']->update(['name'=>'Nama master kategori telah diubah']);
        $this->assertSame('TWK',$item->fresh()->subject_code_snapshot);

        $attempt=$this->attempt($f['member'],$f['package']);
        TryoutAttemptAnswer::query()->create([
            'tryout_attempt_id'=>$attempt->id,'tryout_package_item_id'=>$item->id,
            'answer_label'=>'B','flagged'=>false,
        ]);
        $this->actingAs($f['member'])->post(route('member.tryouts.submit',$attempt))->assertRedirect();
        $rows=app(MemberSubjectAnalysisService::class)->forAttempt($attempt->fresh())->keyBy('code');
        $this->assertSame(1,$rows['TWK']->total);
        $this->assertSame(1,$rows['TWK']->correct);
        $this->assertSame($originalSubjectName,$rows['TWK']->name);
        $this->assertFalse($rows->has('TIU'));
        $this->get(route('member.tryouts.result',$attempt))->assertOk()->assertSee($originalSubjectName);
    }

    public function test_failed_or_late_autosave_never_reopens_a_finished_attempt(): void
    {
        $f=$this->fixture();
        $attempt=$this->attempt($f['member'],$f['package']);
        $item=$f['item'];
        $this->actingAs($f['member']);
        $this->patchJson(route('member.tryouts.save',$attempt),[
            'item_id'=>$item->id,'answer'=>'B','flagged'=>false,
        ])->assertOk()->assertJsonPath('saved',true);
        $attempt->forceFill(['expires_at'=>now()->subSecond()])->save();
        $this->patchJson(route('member.tryouts.save',$attempt),[
            'item_id'=>$item->id,'answer'=>'A','flagged'=>true,
        ])->assertStatus(409);
        $this->assertDatabaseHas('tryout_attempt_answers',[
            'tryout_attempt_id'=>$attempt->id,'tryout_package_item_id'=>$item->id,'answer_label'=>'B',
        ]);
        $this->assertSame('timed_out',$attempt->fresh()->status);
        $this->assertSame(1,(int)$attempt->fresh()->correct_count);
        $this->post(route('member.tryouts.submit',$attempt))->assertRedirect();
        $this->assertSame(1,(int)$attempt->fresh()->correct_count);
    }

    public function test_unpublished_draft_has_no_snapshot_until_publication(): void
    {
        $f=$this->fixture();
        $package=TryoutPackage::query()->create([
            'code'=>'TRY-DRAFT-SNAPSHOT','name'=>'Draft untuk versi yang diterbitkan',
            'duration_minutes'=>10,'max_attempts'=>1,'status'=>'draft',
        ]);
        $item=TryoutPackageItem::query()->create([
            'tryout_package_id'=>$package->id,
            'question_version_id'=>$f['version']->id,'sort_order'=>1,
        ]);
        $this->assertNull($item->subject_code_snapshot);
        app(TryoutEditorialService::class)->publish($package,$f['admin']);
        $this->assertSame('TWK',$item->fresh()->subject_code_snapshot);
    }
}
