<?php
namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiapegawaiMemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_private_and_participant_only(): void
    {
        $this->get(route('member.profile.edit'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role'=>'superadmin','status'=>'active']))
            ->get(route('member.profile.edit'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'participant','status'=>'pending']))
            ->get(route('member.profile.edit'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'participant','status'=>'active']))
            ->get(route('member.profile.edit'))->assertOk()->assertSee('Profil saya');
    }

    public function test_only_allowlisted_profile_fields_can_change(): void
    {
        $member=User::factory()->create(['role'=>'participant','status'=>'active','name'=>'Nama Lama']);
        $this->actingAs($member)->patch(route('member.profile.update'), [
            'name'=>'Nama Baru','whatsapp_number'=>'+6281234567890','email'=>'changed@example.org',
            'status'=>'superadmin','role'=>'superadmin',
        ])->assertRedirect(route('member.profile.edit'));
        $updated=$member->fresh();
        $this->assertSame('Nama Baru',$updated->name);
        $this->assertSame('+6281234567890',$updated->whatsapp_number);
        $this->assertNotSame('changed@example.org',$updated->email);
        $this->assertSame('participant',$updated->role);
        $this->assertSame('active',$updated->status);
        $this->assertSame(1,AuditLog::query()->where('action','participant.profile_updated')->count());
    }

    public function test_password_requires_current_password_and_never_is_written_to_audit(): void
    {
        $member=User::factory()->create(['role'=>'participant','status'=>'active','password'=>'PasswordAwal1234']);
        $this->actingAs($member)->post(route('member.profile.password'),[
            'current_password'=>'PasswordSalah1234','password'=>'PasswordBaru1234',
            'password_confirmation'=>'PasswordBaru1234',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('PasswordAwal1234',$member->fresh()->password));
        $this->actingAs($member)->post(route('member.profile.password'),[
            'current_password'=>'PasswordAwal1234','password'=>'PasswordBaru1234',
            'password_confirmation'=>'PasswordBaru1234',
        ])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertTrue(Hash::check('PasswordBaru1234',$member->fresh()->password));
        $audit=AuditLog::query()->where('action','participant.password_changed')->firstOrFail();
        $encoded=json_encode($audit->toArray());
        $this->assertStringNotContainsString('PasswordAwal1234',$encoded);
        $this->assertStringNotContainsString('PasswordBaru1234',$encoded);
    }
}
