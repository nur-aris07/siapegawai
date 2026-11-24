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

class SiapegawaiPilotAccessTest extends TestCase
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
                'subject_code_snapshot'=>'TIU','subject_name_snapshot'=>'TIU', 'topic_name_snapshot'=>'Topik uji']);
        }
        $quiz->update(['status'=>'published','current_version_id'=>$v->id]);
        return $quiz->fresh();
    }


    private function mediaFixture(int $versionId, string $slot): \App\Models\QuestionVersionMedia
    {
        $bytes=file_get_contents(base_path('database/fixtures/fig-p3/pattern-stem.png'));
        $hex=bin2hex(random_bytes(24)); $key='questions/'.substr($hex,0,2).'/'.$hex.'.png';
        \Illuminate\Support\Facades\Storage::disk('question_media_private')->put($key,$bytes);
        $size=getimagesizefromstring($bytes);
        $id=DB::table('question_version_media')->insertGetId(['question_version_id'=>$versionId,'slot'=>$slot,'position'=>1,
            'object_key'=>$key,'sha256'=>hash('sha256',$bytes),'mime_type'=>'image/png','byte_size'=>strlen($bytes),
            'width_px'=>$size[0],'height_px'=>$size[1],'alt_text'=>'Gambar QA sintetis','created_at'=>now(),'updated_at'=>now()]);
        return \App\Models\QuestionVersionMedia::query()->findOrFail($id);
    }

    private function noWrites(callable $check): void
    {
        $writes=[];
        DB::listen(static function($query)use(&$writes){if(preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i',$query->sql))$writes[]=$query->sql;});
        $check(); $this->assertSame([], $writes, 'Media requests must be read-only.');
    }

    private function pilot(QuickQuiz $quiz): \App\Services\PilotAccessService
    {
        $this->quota(3);
        $anchor=now('UTC')->startOfSecond();
        $values=['mode'=>true,'admission_enabled'=>true,'starts_enabled'=>true,'mail_enabled'=>true,'edition_version_id'=>$quiz->current_version_id,'cohort_code'=>'QA-PILOT','window_start'=>$anchor->copy()->subDay()->format('Y-m-d\TH:i:s\Z'),'window_end'=>$anchor->copy()->addDays(6)->format('Y-m-d\TH:i:s\Z'),'policy_version'=>'QA-L1','research_version'=>'QA-R1','effective_at'=>now('UTC')->subDay()->toIso8601String()];
        foreach (['privacy','terms','research'] as $doc) $values[$doc.'_sha256']=hash_file('sha256',resource_path('pilot/'.$doc.'.txt'));
        config(['pilot'=>$values+config('pilot'),'siapegawai.privacy_version'=>'QA-L1','siapegawai.privacy_approved'=>true,'siapegawai.terms_approved'=>true,'mail.default'=>'array','siapegawai.registration_enabled'=>false,'siapegawai.google_auth_enabled'=>false,'siapegawai.account_email_enabled'=>false]);
        return app(\App\Services\PilotAccessService::class);
    }
    public function test_invite_consent_exact_start_revoke_and_preserve_attempt(): void
    {
        $quiz=$this->published('QQ-EXAM-1',90); $other=$this->published('OTHER'); $p=$this->pilot($quiz);
        \Illuminate\Support\Facades\Storage::fake('question_media_private');
        $item=$quiz->currentVersion->items()->orderBy('sort_order')->firstOrFail();
        $later=$quiz->currentVersion->items()->where('sort_order',2)->firstOrFail();
        $stem=$this->mediaFixture($item->question_version_id,'stem');
        $explanation=$this->mediaFixture($item->question_version_id,'explanation');
        $laterStem=$this->mediaFixture($later->question_version_id,'stem');
        \Illuminate\Support\Facades\Notification::fake();
        $admin=$this->member('superadmin');
        $this->actingAs($admin)->post(route('admin.participants.store'),['name'=>'QA Pilot','email'=>'pilot@example.test'])->assertRedirect();
        $u=User::query()->where('email','pilot@example.test')->sole();
        $this->assertNotNull($p->grant($u));
        $this->assertDatabaseCount('consent_records',0);
        $this->post(route('logout'));
        $token=\Illuminate\Support\Facades\Password::createToken($u);
        $this->post(route('password.update'),['email'=>$u->email,'token'=>$token,'password'=>'SyntheticQA12345','password_confirmation'=>'SyntheticQA12345'])->assertRedirect();
        $this->post(route('login.store'),['email'=>$u->email,'password'=>'SyntheticQA12345'])->assertRedirect();
        $this->postJson(route('participant.invitation.accept'),['consent'=>1,'binding'=>'stale'])->assertUnprocessable();
        $this->assertDatabaseCount('consent_records',0);
        $this->post(route('participant.invitation.accept'),['consent'=>1,'binding'=>$p->binding()])->assertRedirect();
        $this->assertTrue($p->currentConsent($u->fresh()));
        $this->assertSame('active',$u->fresh()->status);
        $this->get(route('member.quick-quizzes.show',$quiz))->assertOk()->assertSee('name="expected_version"',false);
        $this->get(route('member.quick-quizzes.show',$other))->assertNotFound();
        $this->post(route('member.quick-quizzes.start',$other),['start_key'=>'pilot-other-000001','expected_version'=>$other->current_version_id])->assertNotFound();
        $this->assertDatabaseCount('quick_quiz_attempts',0);
        $this->post(route('member.quick-quizzes.start',$quiz),['start_key'=>'pilot-valid-000001','expected_version'=>$quiz->current_version_id])->assertRedirect();
        $a=QuickQuizAttempt::query()->sole();
        $this->assertTrue($p->admitted($u->fresh(),$a));
        $stemUrl=route('member.quick-quizzes.media',[$a,$item,$stem]);
        $explanationUrl=route('member.quick-quizzes.media',[$a,$item,$explanation]);
        $this->noWrites(function()use($stemUrl,$explanationUrl,$a,$later,$laterStem){
            $response=$this->get($stemUrl)->assertOk()->assertHeader('Content-Type','image/png')->assertHeader('X-Content-Type-Options','nosniff');
            $this->assertStringContainsString('no-store',(string)$response->headers->get('Cache-Control'));
            $this->get($explanationUrl)->assertNotFound();
            $this->get(route('member.quick-quizzes.media',[$a,$later,$laterStem]))->assertNotFound();
        });
        $this->postJson(route('member.quick-quizzes.answer',$a),['item_id'=>$item->id,'answer'=>'A'])->assertOk()->assertJsonPath('phase','feedback');
        $this->noWrites(fn()=> $this->get($explanationUrl)->assertOk());
        $start=$p->grant($u)->starts_at->format('Y-m-d H:i:s');
        $p->revoke($u,$admin,'QA revoke');
        $this->assertSame($start,$p->grant($u)->starts_at->format('Y-m-d H:i:s'));
        $this->post(route('member.quick-quizzes.start',$quiz),['start_key'=>'pilot-valid-000001','expected_version'=>$quiz->current_version_id])->assertForbidden();
        $this->assertDatabaseCount('quick_quiz_attempts',1);
        $this->assertDatabaseCount('qq_quota_usages',1);
        $this->assertTrue($p->admitted($u->fresh(),$a->fresh()));
        $this->noWrites(fn()=> $this->get($explanationUrl)->assertOk());
        $foreign=$this->member();
        $this->actingAs($foreign);
        $this->noWrites(fn()=> $this->get($stemUrl)->assertNotFound());
        $this->actingAs($u->fresh());
        $this->get(route('member.classes.index'))->assertNotFound();
        $this->get(route('member.quick-quizzes.take',$a))->assertOk();
        config(['pilot.privacy_sha256'=>str_repeat('0',64)]);
        $this->getJson(route('member.quick-quizzes.take',$a))->assertForbidden();
        $this->noWrites(fn()=> $this->get($explanationUrl)->assertNotFound());
        // Reconsent must append, never rewrite the previous record or re-enable new starts.
        config(['pilot.privacy_sha256'=>hash_file('sha256',resource_path('pilot/privacy.txt')),
            'pilot.policy_version'=>'QA-L2','siapegawai.privacy_version'=>'QA-L2']);
        $this->get(route('member.quick-quizzes.take',$a))->assertRedirect(route('registration.status'));
        $this->get(route('registration.status'))->assertOk();
        $this->post(route('participant.invitation.accept'),['consent'=>1,'binding'=>$p->binding()])->assertRedirect();
        $this->assertDatabaseHas('consent_records',['user_id'=>$u->id,'policy_version'=>'QA-L1','source'=>'admin_invitation']);
        $this->assertDatabaseHas('consent_records',['user_id'=>$u->id,'policy_version'=>'QA-L2','source'=>'pilot_reconsent']);
        $this->assertDatabaseCount('consent_records',2);
        $this->get(route('member.quick-quizzes.take',$a))->assertOk();
        $this->post(route('member.quick-quizzes.start',$quiz),['start_key'=>'pilot-valid-000002','expected_version'=>$quiz->current_version_id])->assertForbidden();
        $this->assertDatabaseCount('quick_quiz_attempts',1);
        $this->assertDatabaseCount('qq_quota_usages',1);
        // The consent endpoint has its own 5/min bucket. The QQ answer/start
        // traffic above must not exhaust it, but repeated consent submissions
        // must still be throttled (never silently disable the rate limiter).
        $this->postJson(route('participant.invitation.accept'),['consent'=>1,'binding'=>'stale'])->assertUnprocessable();
        $this->postJson(route('participant.invitation.accept'),['consent'=>1,'binding'=>'stale'])->assertUnprocessable();
        $this->postJson(route('participant.invitation.accept'),['consent'=>1,'binding'=>'stale'])->assertStatus(429);
        $this->assertDatabaseCount('consent_records',2);
    }
    public function test_defaults_and_bad_policy_fail_closed(): void
    {
        $p=app(\App\Services\PilotAccessService::class); $this->assertFalse($p->enabled());
        $quiz=$this->published(); $p=$this->pilot($quiz);
        $this->assertTrue($p->legalReady());
        config(['pilot.terms_sha256'=>str_repeat('1',64)]); $this->assertFalse($p->legalReady());
        $this->actingAs($this->member('superadmin'))->post(route('admin.participants.store'),['name'=>'QA invalid','email'=>'invalid@example.test'])->assertForbidden();
        $this->assertDatabaseCount('access_grants',0);
    }
    public function test_admission_cap_six_fails_closed_without_seventh_account(): void
    {
        $quiz=$this->published('QQ-CAP-1');$p=$this->pilot($quiz);
        config(['pilot.max_participants'=>6]);
        $admin=$this->member('superadmin');
        for ($i=1;$i<=6;$i++) {
            DB::transaction(function()use($p,$admin,$i){
                $p->lockAdmission();
                $u=$this->member('participant','pending');
                $p->issue($u,$admin);
            },3);
        }
        $this->assertSame(6,DB::table('access_grants')->where('grant_type','pilot_qq')->count());
        try {
            DB::transaction(fn()=> $p->lockAdmission(),3);
            $this->fail('Admission cap must stop a seventh invite.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409,$e->getStatusCode());
        }
        $this->assertSame(6,DB::table('access_grants')->where('grant_type','pilot_qq')->count());
    }

    public function test_noncohort_cannot_access_private_pilot(): void
    {
        $quiz=$this->published(); $this->pilot($quiz);
        $this->actingAs($this->member())->get(route('member.quick-quizzes.index'))->assertForbidden();
        $this->post(route('member.quick-quizzes.start',$quiz),['start_key'=>'noncohort-key-0001','expected_version'=>$quiz->current_version_id])->assertForbidden();
        $this->assertDatabaseCount('qq_quota_usages',0);
    }
}
