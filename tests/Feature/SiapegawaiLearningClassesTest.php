<?php
namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiLearningClassesTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role='participant',string $status='active'): User
    {
        return User::factory()->create(['role'=>$role,'status'=>$status]);
    }
    private function draft(User $admin): LearningClass
    {
        $this->actingAs($admin)->post(route('admin.classes.store'),[
            'title'=>'Kelas Persiapan TWK','description'=>'Kelas belajar TWK dengan materi yang telah ditinjau.',
        ])->assertRedirect();
        return LearningClass::query()->firstOrFail();
    }
    private function open(LearningClass $class,User $admin): LearningMaterial
    {
        $this->actingAs($admin)->post(route('admin.classes.material.store',$class),[
            'title'=>'Dasar Pancasila', 'content'=>'Materi awal tentang landasan negara dan latihan TWK yang terstruktur.',
            'sort_order'=>1,
        ])->assertRedirect();
        $material=LearningMaterial::query()->firstOrFail();
        $this->post(route('admin.classes.material.publish',[$class,$material]))->assertRedirect();
        $this->put(route('admin.classes.update',$class),[
            'title'=>$class->title,'description'=>$class->description,'syllabus'=>'Ulasan Pancasila.',
            'status'=>'open','capacity'=>1,
        ])->assertRedirect();
        return $material;
    }
    public function test_only_admin_creates_classes_and_empty_class_cannot_open(): void
    {
        $this->get(route('admin.classes.index'))->assertRedirect(route('login'));
        $member=$this->person();
        $this->actingAs($member)->get(route('admin.classes.index'))->assertForbidden();
        $admin=$this->person('operational_admin');
        $class=$this->draft($admin);
        $this->assertSame('draft',$class->status);
        $this->put(route('admin.classes.update',$class),[
            'title'=>$class->title,'description'=>$class->description,'status'=>'open',
        ])->assertSessionHasErrors('status');
        $this->assertSame('draft',$class->fresh()->status);
    }
    public function test_member_can_only_read_published_materials_with_active_enrollment(): void
    {
        $root=$this->person('superadmin');$member=$this->person();$other=$this->person();
        $class=$this->draft($root);
        $this->open($class,$root);
        $this->actingAs($other)->get(route('member.classes.show',$class))->assertNotFound();
        $this->actingAs($root)->post(route('admin.classes.enroll',$class),[
            'user_id'=>$member->id,'reason'=>'Akses ujicoba pembelajaran internal untuk peserta.','confirmed'=>'1',
        ])->assertRedirect();
        $this->actingAs($member)->get(route('member.classes.index'))->assertOk()->assertSee('Kelas Persiapan TWK');
        $this->get(route('member.classes.show',$class))->assertOk()->assertSee('Dasar Pancasila');
        $class->materials()->create(['title'=>'Draft rahasia','content'=>'Konten belum diperiksa dan tidak boleh dibaca peserta.','status'=>'draft']);
        $this->get(route('member.classes.show',$class))->assertDontSee('Draft rahasia');
        $this->actingAs($root)->post(route('admin.classes.revoke',[$class,$member]),[
            'reason'=>'Akses pengujian ditutup setelah verifikasi internal.','confirmed'=>'1',
        ])->assertRedirect();
        $this->actingAs($member)->get(route('member.classes.show',$class))->assertNotFound();
        $this->get(route('member.classes.index'))->assertDontSee('Kelas Persiapan TWK');
        $this->assertSame('revoked',ClassEnrollment::query()->firstOrFail()->status);
        $this->assertSame(1,AuditLog::query()->where('action','class.enrollment_revoked')->count());
    }
    public function test_only_superadmin_can_enroll_and_capacity_and_pending_are_enforced(): void
    {
        $root=$this->person('superadmin');$admin=$this->person('operational_admin');
        $member=$this->person();$memberTwo=$this->person();$pending=$this->person('participant','pending');
        $class=$this->draft($root);$this->open($class,$root);
        $url=route('admin.classes.enroll',$class);
        $input=['user_id'=>$member->id,'reason'=>'Pemberian akses kelas atas persetujuan pengelola.','confirmed'=>'1'];
        $this->actingAs($admin)->post($url,$input)->assertForbidden();
        $this->actingAs($root)->post($url,[...$input,'user_id'=>$pending->id])->assertSessionHasErrors('user_id');
        $this->post($url,$input)->assertRedirect();
        $this->post($url,[...$input,'user_id'=>$memberTwo->id])->assertSessionHasErrors('user_id');
        $this->assertSame(1,ClassEnrollment::query()->where('status','active')->count());
    }
    public function test_material_html_is_escaped_in_member_view(): void
    {
        $root=$this->person('superadmin');$member=$this->person();
        $class=$this->draft($root);
        $material=$class->materials()->create([
            'title'=>'Materi HTML','content'=>'Teks aman <script>alert(1)</script> untuk pengujian kelas.',
            'status'=>'published','published_at'=>now(),
        ]);
        $class->update(['status'=>'open']);
        ClassEnrollment::query()->create([
            'class_id'=>$class->id,'user_id'=>$member->id,'status'=>'active',
            'approved_by'=>$root->id,'approved_at'=>now(),
        ]);
        $this->actingAs($member)->get(route('member.classes.show',$class))
            ->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
    }
}
