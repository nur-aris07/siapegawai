<?php
namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\Topic;
use App\Models\TryoutPackage;
use App\Models\User;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiTryoutTemplateTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(SiapegawaiSubjectsSeeder::class); }
    private function admin(): User { return User::factory()->create(['role'=>'superadmin','status'=>'active']); }
    private function questionPayload(string $body='Apa alasan pentingnya persatuan dalam negara Indonesia?'): array
    {
        return [
            'code'=>'TWK-001','topic_id'=>Topic::query()->firstOrFail()->id,
            'body'=>$body,'explanation'=>'Persatuan bangsa merupakan dasar hidup bersama negara yang berdaulat.',
            'option_a'=>'Menguatkan persatuan','option_b'=>'Memisahkan berbagai daerah',
            'option_c'=>'Mengabaikan hak warga','option_d'=>'Menghapus musyawarah',
            'option_e'=>'','correct_option'=>'A',
        ];
    }
    private function publishedQuestion(): Question
    {
        $this->post(route('questions.admin.store'),$this->questionPayload())->assertRedirect();
        $q=Question::query()->firstOrFail();
        $this->post(route('questions.admin.publish',$q),['confirmed_review'=>1])->assertRedirect();
        return $q->fresh();
    }
    private function payload(array $versionIds): array
    {
        return ['code'=>'SIM-SKD-01','name'=>'Simulasi SKD Awal',
            'description'=>'Latihan terjadwal untuk peserta yang terdaftar.',
            'duration_minutes'=>45,'max_attempts'=>2,'version_ids'=>$versionIds];
    }

    public function test_draft_publish_pins_version_even_after_question_revision(): void
    {
        $this->actingAs($this->admin());
        $question=$this->publishedQuestion();
        $originalVersion=$question->current_version_id;
        $this->post(route('tryouts.admin.store'),$this->payload([$originalVersion]))->assertRedirect();
        $package=TryoutPackage::query()->firstOrFail();
        $this->assertSame('draft',$package->status);
        $this->assertSame($originalVersion,(int)$package->items()->firstOrFail()->question_version_id);
        $this->post(route('tryouts.admin.publish',$package),['confirmed_review'=>1])->assertRedirect();
        $this->assertSame('published',$package->fresh()->status);
        $update=$this->questionPayload('Bagaimana persatuan dapat dijaga untuk setiap warga negara?');
        $this->put(route('questions.admin.update',$question),$update)->assertRedirect();
        $this->post(route('questions.admin.publish',$question),['confirmed_review'=>1])->assertRedirect();
        $this->assertNotEquals($originalVersion,$question->fresh()->current_version_id);
        $this->assertSame($originalVersion,(int)$package->items()->firstOrFail()->question_version_id);
        $this->put(route('tryouts.admin.update',$package),$this->payload([$originalVersion]))->assertForbidden();
        $this->assertDatabaseHas('audit_logs',['action'=>'tryout.publish','subject_id'=>$package->id]);
        $this->get(route('tryouts.admin.edit',$package))->assertOk()->assertSee('v1');
    }

    public function test_unpublished_version_cannot_be_selected_and_empty_draft_cannot_publish(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('questions.admin.store'),$this->questionPayload())->assertRedirect();
        $unpublished=Question::query()->firstOrFail()->latestVersion->id;
        $this->post(route('tryouts.admin.store'),$this->payload([$unpublished]))->assertSessionHasErrors('version_ids');
        $this->assertDatabaseCount('tryout_packages',0);
        $this->post(route('tryouts.admin.store'),$this->payload([]))->assertRedirect();
        $package=TryoutPackage::query()->firstOrFail();
        $this->post(route('tryouts.admin.publish',$package),['confirmed_review'=>1])->assertSessionHasErrors('publish');
        $this->assertSame('draft',$package->fresh()->status);
    }

    public function test_ajax_listing_and_participant_permissions(): void
    {
        $this->get(route('tryouts.admin.index'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role'=>'participant','status'=>'active']))
            ->get(route('tryouts.admin.index'))->assertForbidden();
        $this->get(route('tryouts.admin.search'))->assertForbidden();
        $this->actingAs($this->admin());
        $this->get(route('tryouts.admin.index'))->assertOk()->assertSee('Susunan tryout');
        $this->withHeaders(['X-Requested-With'=>'XMLHttpRequest','Accept'=>'application/json'])
            ->get(route('tryouts.admin.index').'?draw=1&start=0&length=10')
            ->assertOk()->assertJsonStructure(['draw','recordsTotal','recordsFiltered','data']);
    }

    public function test_duplicate_question_rejected_even_when_one_version_already_pinned(): void
    {
        $this->actingAs($this->admin());
        $q=$this->publishedQuestion();
        $v1=$q->current_version_id;
        $this->post(route('tryouts.admin.store'),$this->payload([$v1]))->assertRedirect();
        $package=TryoutPackage::query()->firstOrFail();
        $this->put(route('questions.admin.update',$q),$this->questionPayload('Apakah setiap daerah wajib menjaga persatuan bangsa Indonesia?'))->assertRedirect();
        $this->post(route('questions.admin.publish',$q),['confirmed_review'=>1])->assertRedirect();
        $v2=$q->fresh()->current_version_id;
        $this->assertNotEquals($v1,$v2);
        $this->put(route('tryouts.admin.update',$package),$this->payload([$v1,$v2]))->assertSessionHasErrors('version_ids');
        $this->assertSame(1,$package->items()->count());
        $this->assertSame((int)$v1,(int)$package->items()->firstOrFail()->question_version_id);
    }
}
