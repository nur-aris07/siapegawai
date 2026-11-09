<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiParticipantOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'participant', string $status = 'active'): User
    {
        return User::factory()->create([
            'role' => $role, 'status' => $status,
            'activated_at' => $status === 'active' ? now()->subDay() : null,
        ]);
    }

    public function test_directory_uses_one_url_for_blade_and_yajra_json_without_admin_accounts(): void
    {
        $admin = $this->account('operational_admin');
        $p = $this->account();
        $this->actingAs($admin)->get(route('admin.participants.index'))
            ->assertOk()->assertSee('Direktori peserta');
        $result = $this->actingAs($admin)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.participants.index', ['draw' => 1, 'start' => 0, 'length' => 10]));
        $result->assertOk()->assertJsonStructure(['draw','recordsTotal','recordsFiltered','data'])
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.id', $p->id);
        $this->assertSame('participant', $p->role);
    }

    public function test_guest_participant_and_suspended_admin_cannot_read_directory(): void
    {
        $this->get(route('admin.participants.index'))->assertRedirect('/login');
        $this->actingAs($this->account())->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])
            ->get(route('admin.participants.index'))->assertForbidden();
        $this->actingAs($this->account('operational_admin', 'suspended'))
            ->get(route('admin.participants.index'))->assertForbidden();
    }

    public function test_admin_may_view_detail_but_only_superadmin_can_change_status(): void
    {
        $admin = $this->account('operational_admin');
        $participant = $this->account();
        $this->actingAs($admin)->get(route('admin.participants.show', $participant))
            ->assertOk()->assertSee($participant->name)->assertDontSee('Tangguhkan peserta');
        $this->actingAs($admin)->post(route('admin.participants.status', $participant), [
            'action'=>'suspend','reason'=>'Pemeriksaan keamanan akun peserta.','confirmed'=>'1',
        ])->assertForbidden();
        $this->assertSame('active', $participant->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_superadmin_can_suspend_and_restore_with_reason_and_audit_without_changing_history(): void
    {
        $root = $this->account('superadmin');
        $participant = $this->account();
        $url = route('admin.participants.status', $participant);
        $this->actingAs($root)->post($url, [
            'action'=>'suspend','reason'=>'Akun sedang ditinjau berdasarkan laporan keamanan.','confirmed'=>'1',
        ])->assertRedirect(route('admin.participants.show', $participant));
        $this->assertSame('suspended', $participant->fresh()->status);
        $this->assertNotNull($participant->fresh()->suspended_at);
        $this->assertSame(1, AuditLog::query()->where('subject_id', $participant->id)
            ->where('action','participant.suspend')->count());
        $this->actingAs($participant)->get(route('member.tryouts.index'))->assertForbidden();
        $this->actingAs($root)->post($url, [
            'action'=>'restore','reason'=>'Investigasi selesai; akun dapat digunakan kembali.','confirmed'=>'1',
        ])->assertRedirect(route('admin.participants.show', $participant));
        $this->assertSame('active', $participant->fresh()->status);
        $this->assertNull($participant->fresh()->suspended_at);
        $this->assertSame(1, AuditLog::query()->where('subject_id', $participant->id)
            ->where('action','participant.restore')->count());
        $this->actingAs($participant)->get(route('member.tryouts.index'))->assertOk();
    }

    public function test_pending_and_rejected_accounts_cannot_bypass_registration_review(): void
    {
        $root = $this->account('superadmin');
        foreach (['pending','rejected'] as $state) {
            $participant = $this->account('participant', $state);
            $this->actingAs($root)->post(route('admin.participants.status', $participant), [
                'action'=>'restore','reason'=>'Aktifkan tanpa melakukan verifikasi resmi.','confirmed'=>'1',
            ])->assertSessionHasErrors('reason');
            $this->assertSame($state, $participant->fresh()->status);
        }
    }

    public function test_replay_and_invalid_confirmation_do_not_write_audit(): void
    {
        $root = $this->account('superadmin');
        $participant = $this->account();
        $url = route('admin.participants.status', $participant);
        $this->actingAs($root)->post($url, [
            'action'=>'suspend','reason'=>'Pendek','confirmed'=>'1',
        ])->assertSessionHasErrors('reason');
        $this->actingAs($root)->post($url, [
            'action'=>'suspend','reason'=>'Tindakan harus dikonfirmasi.',
        ])->assertSessionHasErrors('confirmed');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->actingAs($root)->post($url, [
            'action'=>'suspend','reason'=>'Laporan keamanan sedang ditindaklanjuti.','confirmed'=>'1',
        ])->assertRedirect();
        $this->actingAs($root)->post($url, [
            'action'=>'suspend','reason'=>'Permintaan ulang yang tidak boleh diproses.','confirmed'=>'1',
        ])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_nonparticipant_accounts_are_not_visible_or_mutable_in_participant_directory(): void
    {
        $root = $this->account('superadmin');
        $otherAdmin = $this->account('operational_admin');
        $this->actingAs($root)->get(route('admin.participants.show', $otherAdmin))->assertNotFound();
        $this->actingAs($root)->post(route('admin.participants.status', $otherAdmin), [
            'action'=>'suspend','reason'=>'Tidak boleh mengubah akun administrator.','confirmed'=>'1',
        ])->assertNotFound();
        $this->assertSame('active', $otherAdmin->fresh()->status);
    }

    public function test_suspended_participant_cannot_log_in_again(): void
    {
        $participant = $this->account();
        $participant->password = 'UjiRahasia123456';
        $participant->status = 'suspended';
        $participant->suspended_at = now();
        $participant->save();
        $this->post(route('login.store'), [
            'email' => $participant->email, 'password' => 'UjiRahasia123456',
        ])->assertForbidden();
        $this->assertGuest();
    }

    public function test_participant_name_is_escaped_in_detail_view(): void
    {
        $admin = $this->account('operational_admin');
        $participant = $this->account();
        $participant->name = '<img src=x onerror=alert(1)>';
        $participant->save();
        $this->actingAs($admin)->get(route('admin.participants.show', $participant))
            ->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }
}
