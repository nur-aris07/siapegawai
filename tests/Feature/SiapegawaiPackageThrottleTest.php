<?php

namespace Tests\Feature;

use App\Models\MemberPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiPackageThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_request_throttle_stops_sixth_attempt_without_creating_grants(): void
    {
        $member = User::factory()->create(['role' => 'participant', 'status' => 'active']);
        $plan = MemberPlan::query()->where('code', 'ABDI_NEGARA')->firstOrFail();
        $this->actingAs($member);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            // Planned plan is rejected by business rules; requests still count for anti-abuse.
            $this->post(route('member.packages.request.store', $plan), ['confirmed'=>1])
                ->assertSessionHasErrors('package');
        }
        $this->post(route('member.packages.request.store', $plan), ['confirmed'=>1])->assertStatus(429);
        $this->assertDatabaseCount('package_upgrade_requests',0);
        $this->assertDatabaseCount('access_grants',0);
    }
}
