<?php
namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\SiapegawaiSubjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiSubjectCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->seed(SiapegawaiSubjectsSeeder::class); }
    private function admin(): User { return User::factory()->create(['role'=>'operational_admin','status'=>'active']); }
    private function questionPayload(int $topic): array
    {
        return ['code'=>'TWK-PERIKSA','topic_id'=>$topic,'body'=>'Apa maksud persatuan dalam negara kesatuan Indonesia?',
            'explanation'=>'Satu bangsa memiliki tujuan yang dibangun secara bersama-sama.',
            'option_a'=>'Menjaga persatuan bangsa','option_b'=>'Memisahkan daerah satu dan lain',
            'option_c'=>'Menghapus bahasa daerah','option_d'=>'Mengabaikan masyarakat',
            'option_e'=>'','correct_option'=>'A'];
    }

    public function test_admin_can_manage_subjects_and_topics_with_audit(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('subjects.admin.index'))->assertOk()->assertSee('Kategori materi');
        $this->post(route('subjects.admin.store'),['code'=>'BID','name'=>'Bidang Teknologi','sort_order'=>10])->assertRedirect();
        $subject=Subject::query()->where('code','BID')->firstOrFail();
        $this->post(route('subjects.admin.topics.store',$subject), ['name'=>'Keamanan Sistem','sort_order'=>2])->assertRedirect();
        $topic=$subject->topics()->firstOrFail();
        $this->assertSame('keamanan-sistem',$topic->slug);
        $this->put(route('subjects.admin.topics.update',$topic),['name'=>'Keamanan Sistem dan Jaringan','status'=>'inactive','sort_order'=>2])->assertRedirect();
        $this->assertSame('inactive',$topic->fresh()->status);
        $this->assertDatabaseHas('audit_logs',['action'=>'topic.update','subject_id'=>$topic->id]);
        $this->delete(route('subjects.admin.topics.destroy',$topic),['confirmed_delete'=>1])->assertRedirect();
        $this->delete(route('subjects.admin.destroy',$subject),['confirmed_delete'=>1])->assertRedirect();
        $this->assertDatabaseMissing('subjects',['id'=>$subject->id]);
        $this->assertSame(1,AuditLog::query()->where('action','subject.delete')->count());
    }

    public function test_used_topic_cannot_be_deleted_but_can_be_inactivated(): void
    {
        $this->actingAs($this->admin());
        $topic=Topic::query()->where('slug','umum')->firstOrFail();
        $this->post(route('questions.admin.store'),$this->questionPayload($topic->id))->assertRedirect();
        $this->assertSame(1,Question::query()->count());
        $this->delete(route('subjects.admin.topics.destroy',$topic),['confirmed_delete'=>1])->assertSessionHasErrors('topic');
        $this->put(route('subjects.admin.topics.update',$topic),['name'=>'Umum','status'=>'inactive','sort_order'=>1])->assertRedirect();
        $this->post(route('questions.admin.store'),$this->questionPayload($topic->id))->assertSessionHasErrors('topic_id');
        $this->get(route('questions.admin.edit',Question::query()->firstOrFail()))->assertOk()->assertSee('Umum');
    }

    public function test_participants_cannot_manage_catalog(): void
    {
        $this->get(route('subjects.admin.index'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role'=>'participant','status'=>'active']))
            ->get(route('subjects.admin.index'))->assertForbidden();
        $this->post(route('subjects.admin.store'),['code'=>'NEW','name'=>'Baru'])->assertForbidden();
    }
}
