<?php
namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\ClassPractice;
use App\Models\ClassPracticeAttempt;
use App\Models\ClassPracticeItem;
use App\Models\LearningClass;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\QuestionVersionMedia;
use App\Models\QuickQuiz;
use App\Models\QuickQuizAttempt;
use App\Models\QuickQuizAttemptAnswer;
use App\Models\QuickQuizVersion;
use App\Models\QuickQuizVersionItem;
use App\Models\Topic;
use App\Models\TryoutAttempt;
use App\Models\TryoutPackage;
use App\Models\TryoutPackageItem;
use App\Models\User;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SiapegawaiQuestionContextMediaAccessTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    private QuestionVersion $version;
    private QuestionVersionMedia $stem;
    private QuestionVersionMedia $explanation;
    private QuestionVersionMedia $optionA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiapegawaiSubjectsSeeder::class);
        Storage::fake('question_media_private');
        $this->owner=User::factory()->create(['role'=>'participant','status'=>'active']);
        $question=Question::query()->create(['topic_id'=>Topic::query()->firstOrFail()->id,
            'code'=>'QA-FIG-P3-ACCESS','created_by'=>null,'status'=>'published']);
        $this->version=QuestionVersion::query()->create([
            'question_id'=>$question->id,'version_number'=>1,'body'=>'Perhatikan gambar soal.',
            'explanation'=>'Contoh penjelasan yang hanya boleh tampil sesudah selesai.',
            'review_status'=>'published','published_at'=>now(),
        ]);
        $question->update(['current_version_id'=>$this->version->id]);
        foreach (['A','B','C','D'] as $index=>$label) {
            $this->version->options()->create(['label'=>$label,'body'=>'Pilihan '.$label,
                'sort_order'=>$index+1,'is_correct'=>$index===0]);
        }
        $this->stem=$this->makeMedia('stem','a');
        $this->explanation=$this->makeMedia('explanation','b');
        $this->optionA=$this->makeMedia('option_A','c');
    }
    private function makeMedia(string $slot, string $hex): QuestionVersionMedia
    {
        $bytes=file_get_contents(base_path('database/fixtures/fig-p3/pattern-stem.png'));
        $key='questions/'.$hex.$hex.'/'.str_repeat($hex,48).'.png';
        Storage::disk('question_media_private')->put($key,$bytes);
        $m=new QuestionVersionMedia();$m->question_version_id=$this->version->id;
        $m->slot=$slot;$m->position=1;$m->object_key=$key;
        $m->sha256=hash('sha256',$bytes);$m->mime_type='image/png';
        $m->byte_size=strlen($bytes);$m->width_px=720;$m->height_px=320;
        $m->alt_text='Diagram QA';$m->save();return $m;
    }
    private function qq(): array
    {
        $quiz=QuickQuiz::query()->create(['code'=>'QA-P3-READONLY','status'=>'draft']);
        $version=QuickQuizVersion::query()->create(['quick_quiz_id'=>$quiz->id,'version_number'=>1,
            'status'=>'published','title'=>'QQ Fixture','seconds_per_question'=>60,'published_at'=>now()]);
        $quiz->update(['status'=>'published','current_version_id'=>$version->id]);
        $item=QuickQuizVersionItem::query()->create(['quick_quiz_version_id'=>$version->id,
            'question_version_id'=>$this->version->id,'sort_order'=>1]);
        $attempt=QuickQuizAttempt::query()->create(['user_id'=>$this->owner->id,
            'quick_quiz_version_id'=>$version->id,'status'=>'active','phase'=>'question',
            'start_key'=>str_repeat('z',32),'current_index'=>0,'total_questions'=>1,
            'question_started_at'=>now(),'question_expires_at'=>now()->addSeconds(60),
            'last_seen_at'=>now(),'started_at'=>now()]);
        return [$attempt,$item];
    }
    private function cat(): array
    {
        $package=TryoutPackage::query()->create(['code'=>'QA-P3-CAT','name'=>'CAT QA',
            'duration_minutes'=>20,'max_attempts'=>2,'status'=>'published','published_at'=>now()]);
        $item=TryoutPackageItem::query()->create(['tryout_package_id'=>$package->id,
            'question_version_id'=>$this->version->id,'sort_order'=>1]);
        $attempt=TryoutAttempt::query()->create(['user_id'=>$this->owner->id,
            'tryout_package_id'=>$package->id,'attempt_number'=>1,'status'=>'in_progress',
            'started_at'=>now(),'expires_at'=>now()->addMinutes(20),'total_questions'=>1]);
        return [$attempt,$item];
    }
    private function practice(): array
    {
        $class=LearningClass::query()->create(['slug'=>'qa-fig-p3-access', 'title'=>'QA Figural Kelas',
            'description'=>'Testing','status'=>'open','capacity'=>5]);
        ClassEnrollment::query()->create(['class_id'=>$class->id,'user_id'=>$this->owner->id,'status'=>'active']);
        $practice=ClassPractice::query()->create(['class_id'=>$class->id,'title'=>'Latihan QA',
            'duration_minutes'=>20,'max_attempts'=>2,'status'=>'published','published_at'=>now()]);
        $item=ClassPracticeItem::query()->create(['class_practice_id'=>$practice->id,
            'question_version_id'=>$this->version->id,'sort_order'=>1]);
        $attempt=ClassPracticeAttempt::query()->create(['class_practice_id'=>$practice->id,
            'user_id'=>$this->owner->id,'attempt_number'=>1,'status'=>'in_progress',
            'started_at'=>now(),'expires_at'=>now()->addMinutes(20),'total_questions'=>1]);
        return [$class,$attempt,$item];
    }
    private function assertZeroWrites(callable $action): void
    {
        $writes=[];
        DB::listen(static function ($query) use (&$writes): void {
            if (preg_match('/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $query->sql)) $writes[]=$query->sql;
        });
        $action();
        $this->assertSame([], $writes, 'Image GET must not mutate any database tables.');
    }
    public function test_qq_question_allows_stem_but_refuses_early_explanation_and_foreign_user(): void
    {
        [$attempt,$item]=$this->qq();
        $url=route('member.quick-quizzes.media',[$attempt,$item,$this->stem]);
        $this->actingAs($this->owner);
        $this->assertZeroWrites(function () use ($url,$attempt,$item): void {
            $response=$this->get($url)->assertOk()->assertHeader('Content-Type','image/png');
            $cache=strtolower((string)$response->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', $cache);
            $this->assertStringContainsString('no-store', $cache);
            $this->assertStringNotContainsString('public', $cache);
            $this->get(route('member.quick-quizzes.media',[$attempt,$item,$this->explanation]))->assertNotFound();
        });
        $this->actingAs(User::factory()->create(['role'=>'participant','status'=>'active']))
            ->get($url)->assertNotFound();
    }
    public function test_qq_feedback_requires_locked_answer_and_finished_review_keeps_only_locked_items(): void
    {
        [$attempt,$item]=$this->qq();
        $attempt->update(['phase'=>'feedback','feedback_until'=>now()->addSeconds(5)]);
        $this->actingAs($this->owner)->get(route('member.quick-quizzes.media',[$attempt,$item,$this->explanation]))->assertNotFound();
        QuickQuizAttemptAnswer::query()->create(['quick_quiz_attempt_id'=>$attempt->id,
            'quick_quiz_version_item_id'=>$item->id,'answer_label'=>'A','status'=>'answered',
            'is_correct'=>true,'response_ms'=>100,'locked_at'=>now()]);
        $this->assertZeroWrites(fn()=> $this->get(route('member.quick-quizzes.media',[$attempt,$item,$this->explanation]))->assertOk());
        $attempt->update(['status'=>'abandoned','phase'=>'finished','finished_at'=>now()]);
        $this->assertZeroWrites(fn()=> $this->get(route('member.quick-quizzes.media',[$attempt,$item,$this->explanation]))->assertOk());
    }
    public function test_cat_requires_pin_and_denies_explanation_until_finished_with_no_writes(): void
    {
        [$attempt,$item]=$this->cat();$this->actingAs($this->owner);
        $this->assertZeroWrites(function () use ($attempt,$item): void {
            $this->get(route('member.tryouts.media',[$attempt,$item,$this->stem]))->assertOk();
            $this->get(route('member.tryouts.media',[$attempt,$item,$this->explanation]))->assertNotFound();
            $this->get(route('member.tryouts.media',[$attempt,999999,$this->stem]))->assertNotFound();
        });
        $attempt->update(['status'=>'completed','finished_at'=>now()]);
        $this->assertZeroWrites(fn()=> $this->get(route('member.tryouts.media',[$attempt,$item,$this->explanation]))->assertOk());
    }
    public function test_class_media_revocation_and_regrant_never_mutate_attempt(): void
    {
        [$class,$attempt,$item]=$this->practice();
        $url=route('member.class-practices.media',[$class,$attempt,$item,$this->stem]);
        $this->actingAs($this->owner);
        $this->assertZeroWrites(fn()=> $this->get($url)->assertOk());
        ClassEnrollment::query()->where('class_id',$class->id)->where('user_id',$this->owner->id)->update(['status'=>'revoked']);
        $this->assertZeroWrites(fn()=> $this->get($url)->assertNotFound());
        ClassEnrollment::query()->where('class_id',$class->id)->where('user_id',$this->owner->id)->update(['status'=>'active']);
        $this->assertZeroWrites(fn()=> $this->get($url)->assertOk());
        $this->assertZeroWrites(fn()=> $this->get(route('member.class-practices.media',[$class,$attempt,$item,$this->explanation]))->assertNotFound());
        $attempt->update(['status'=>'completed','finished_at'=>now()]);
        $this->assertZeroWrites(fn()=> $this->get(route('member.class-practices.media',[$class,$attempt,$item,$this->explanation]))->assertOk());
    }
    public function test_expired_active_cat_and_class_attempts_do_not_auto_finish_through_get(): void
    {
        [$cat,$catItem]=$this->cat();[$class,$practice,$classItem]=$this->practice();
        $cat->update(['expires_at'=>now()->subSecond()]);$practice->update(['expires_at'=>now()->subSecond()]);
        $this->actingAs($this->owner);
        $this->assertZeroWrites(function () use ($cat,$catItem,$class,$practice,$classItem): void {
            $this->get(route('member.tryouts.media',[$cat,$catItem,$this->stem]))->assertNotFound();
            $this->get(route('member.class-practices.media',[$class,$practice,$classItem,$this->stem]))->assertNotFound();
        });
        $this->assertSame('in_progress',$cat->fresh()->status);
        $this->assertSame('in_progress',$practice->fresh()->status);
    }
    public function test_cat_and_class_take_render_image_only_choices_without_early_explanation(): void
    {
        [$cat,$catItem]=$this->cat();[$class,$practice,$classItem]=$this->practice();
        $this->actingAs($this->owner);
        $this->get(route('member.tryouts.take',$cat))->assertOk()
            ->assertSee(route('member.tryouts.media',[$cat,$catItem,$this->stem]),false)
            ->assertSee(route('member.tryouts.media',[$cat,$catItem,$this->optionA]),false)
            ->assertSee('fig-participant-figure',false)
            ->assertDontSee(route('member.tryouts.media',[$cat,$catItem,$this->explanation]),false);
        $this->get(route('member.class-practices.take',[$class,$practice]))->assertOk()
            ->assertSee(route('member.class-practices.media',[$class,$practice,$classItem,$this->optionA]),false)
            ->assertDontSee(route('member.class-practices.media',[$class,$practice,$classItem,$this->explanation]),false);
    }
    public function test_class_result_contains_all_choices_and_deferred_explanation_media(): void
    {
        [$class,$attempt,$item]=$this->practice();
        $attempt->update(['status'=>'completed','finished_at'=>now()]);
        $this->actingAs($this->owner)->get(route('member.class-practices.result',[$class,$attempt]))
            ->assertOk()->assertSee('fig-participant-result-options',false)
            ->assertSee(route('member.class-practices.media',[$class,$attempt,$item,$this->explanation]),false)
            ->assertSee('Pilihan A')->assertSee('Pilihan D');
    }
    public function test_participant_images_are_static_and_have_role_based_bounds_across_domains(): void
    {
        [$qq, $qqItem] = $this->qq();
        [$cat, $catItem] = $this->cat();
        [$class, $practice, $classItem] = $this->practice();
        $this->actingAs($this->owner);

        $qqHtml = $this->get(route('member.quick-quizzes.take', $qq))->assertOk()->getContent();
        $catHtml = $this->get(route('member.tryouts.take', $cat))->assertOk()->getContent();
        $classHtml = $this->get(route('member.class-practices.take', [$class, $practice]))->assertOk()->getContent();
        foreach ([$qqHtml, $catHtml, $classHtml] as $html) {
            $this->assertStringContainsString('fig-participant-figure--stem', $html);
            $this->assertStringContainsString('fig-participant-figure--option', $html);
            $this->assertStringNotContainsString('fig-participant-image-link', $html);
            $this->assertStringContainsString('draggable="false"', $html);
            $this->assertStringNotContainsString('Buka gambar ukuran penuh', $html);
        }
        // Each contextual URL is still an authorized image source, NEVER an HTML anchor.
        foreach ([
            [$qqHtml, route('member.quick-quizzes.media', [$qq, $qqItem, $this->optionA])],
            [$catHtml, route('member.tryouts.media', [$cat, $catItem, $this->optionA])],
            [$classHtml, route('member.class-practices.media', [$class, $practice, $classItem, $this->optionA])],
        ] as [$html, $url]) {
            $this->assertStringContainsString('src="'.$url.'"', $html);
            $this->assertStringNotContainsString('href="'.$url.'"', $html);
        }

        $cat->update(['status' => 'completed', 'finished_at' => now()]);
        $practice->update(['status' => 'completed', 'finished_at' => now()]);
        foreach ([
            $this->get(route('member.tryouts.result', $cat))->assertOk()->getContent(),
            $this->get(route('member.class-practices.result', [$class, $practice]))->assertOk()->getContent(),
        ] as $html) {
            $this->assertStringContainsString('fig-participant-figure--explanation', $html);
            $this->assertStringContainsString('fig-participant-figure--option', $html);
            $this->assertStringNotContainsString('fig-participant-image-link', $html);
        }

        $css = file_get_contents(resource_path('css/member-cat.css'));
        $this->assertStringContainsString('--fig-media-max-width:640px', $css);
        $this->assertStringContainsString('--fig-media-max-width:300px', $css);
        $this->assertStringContainsString('--fig-media-max-width:520px', $css);
        $this->assertStringContainsString('max-height:min(52vh,var(--fig-media-max-height))', $css);
    }

    public function test_bad_checksum_is_denied_and_does_not_expose_storage_object_key(): void
    {
        [$attempt,$item]=$this->cat();$this->actingAs($this->owner);
        Storage::disk('question_media_private')->put($this->stem->object_key, 'tampered');
        $this->assertZeroWrites(fn()=> $this->get(route('member.tryouts.media',[$attempt,$item,$this->stem]))->assertNotFound());
    }
}
