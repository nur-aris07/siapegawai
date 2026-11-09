<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiapegawaiAccountStatusRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_participant_session_cannot_keep_access_after_suspension(): void
    {
        $participant = User::factory()->create(['role' => 'participant', 'status' => 'active']);

        $this->actingAs($participant)
            ->get(route('member.tryouts.index'))->assertOk();

        // Update the DB independently of the authenticated model; mimic another admin request.
        DB::table('users')->where('id', $participant->id)->update([
            'status' => 'suspended', 'suspended_at' => now(),
        ]);

        $this->get(route('member.tryouts.index'))->assertForbidden();
        $this->get(route('member.tryouts.history'))->assertForbidden();
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_existing_participant_session_is_allowed_after_explicit_restoration(): void
    {
        $participant = User::factory()->create(['role' => 'participant', 'status' => 'active']);
        $this->actingAs($participant);

        DB::table('users')->where('id', $participant->id)->update([
            'status' => 'suspended', 'suspended_at' => now(),
        ]);
        $this->get(route('member.tryouts.index'))->assertForbidden();

        DB::table('users')->where('id', $participant->id)->update([
            'status' => 'active', 'suspended_at' => null,
        ]);
        $this->get(route('member.tryouts.index'))->assertOk();
    }

    public function test_role_or_account_status_change_revokes_old_admin_session_privileges(): void
    {
        $admin = User::factory()->create(['role' => 'operational_admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        DB::table('users')->where('id', $admin->id)->update(['role' => 'participant']);
        $this->get(route('admin.dashboard'))->assertForbidden();

        DB::table('users')->where('id', $admin->id)->update([
            'role' => 'operational_admin', 'status' => 'suspended', 'suspended_at' => now(),
        ]);
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_pending_participant_dashboard_still_redirects_to_activation_status(): void
    {
        $pending = User::factory()->create(['role' => 'participant', 'status' => 'pending']);
        $this->actingAs($pending)->get(route('dashboard'))
            ->assertRedirect(route('registration.status'));
        $this->get(route('member.tryouts.index'))->assertForbidden();
    }

    public function test_suspended_and_rejected_participants_cannot_use_dashboard_redirect(): void
    {
        foreach (['suspended', 'rejected'] as $status) {
            $participant = User::factory()->create(['role' => 'participant', 'status' => $status]);
            $this->actingAs($participant)->get(route('dashboard'))->assertForbidden();
        }
    }
}
