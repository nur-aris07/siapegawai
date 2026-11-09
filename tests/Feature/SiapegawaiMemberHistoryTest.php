<?php

namespace Tests\Feature;

use App\Models\TryoutAttempt;
use App\Models\TryoutPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiMemberHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role = 'participant', string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function package(string $status = 'published'): TryoutPackage
    {
        return TryoutPackage::query()->create([
            'code' => 'HISTORY-001',
            'name' => 'Tryout Riwayat Contoh',
            'status' => $status,
            'duration_minutes' => 30,
            'max_attempts' => 30,
        ]);
    }

    private function attempt(User $user, TryoutPackage $package, int $number, string $status = 'completed', int $correct = 1): TryoutAttempt
    {
        return TryoutAttempt::query()->create([
            'user_id' => $user->id,
            'tryout_package_id' => $package->id,
            'attempt_number' => $number,
            'status' => $status,
            'started_at' => now()->subMinutes(4),
            'expires_at' => now()->addMinutes(26),
            'finished_at' => $status === 'in_progress' ? null : now(),
            'total_questions' => 2,
            'answered_count' => $status === 'in_progress' ? null : 2,
            'correct_count' => $status === 'in_progress' ? null : $correct,
            'score_percent' => $status === 'in_progress' ? null : ($correct * 50),
        ]);
    }

    public function test_history_requires_active_participant(): void
    {
        $url = route('member.tryouts.history');
        $this->get($url)->assertRedirect('/login');
        $this->actingAs($this->member('superadmin'))->get($url)->assertForbidden();
        $this->actingAs($this->member('participant', 'pending'))->get($url)->assertForbidden();
        $this->actingAs($this->member())->get($url)->assertOk()->assertSee('Belum ada hasil tryout');
    }

    public function test_history_contains_only_owned_attempts_and_keeps_archived_template_results(): void
    {
        $package = $this->package('archived');
        $owner = $this->member();
        $stranger = $this->member();
        $own = $this->attempt($owner, $package, 1, 'completed', 2);
        $other = $this->attempt($stranger, $package, 1, 'completed', 0);

        $this->actingAs($owner)->get(route('member.tryouts.history'))
            ->assertOk()
            ->assertSee('Template diarsipkan')
            ->assertSee('100,0%')
            ->assertSee(route('member.tryouts.result', $own), false)
            ->assertDontSee(route('member.tryouts.result', $other), false);
        $this->get(route('member.tryouts.result', $other))->assertForbidden();
        $this->get(route('member.tryouts.result', $own))->assertOk();
    }

    public function test_progress_aggregates_and_paginates_finished_attempts(): void
    {
        $package = $this->package();
        $owner = $this->member();
        for ($i = 1; $i <= 12; $i++) {
            $this->attempt($owner, $package, $i, $i % 2 === 0 ? 'timed_out' : 'completed', $i % 2 === 0 ? 0 : 2);
        }
        $this->actingAs($owner)->get(route('member.tryouts.history'))
            ->assertOk()
            ->assertSee('12 hasil tersimpan')
            ->assertSee('50,0%')
            ->assertSee('100,0%')
            ->assertSee('Halaman 1 dari 2');
        $this->get(route('member.tryouts.history', ['page' => 2]))
            ->assertOk()
            ->assertSee('Halaman 2 dari 2');
    }

    public function test_elapsed_attempt_is_finalized_by_server_before_progress_calculation(): void
    {
        $package = $this->package();
        $owner = $this->member();
        $attempt = $this->attempt($owner, $package, 1, 'in_progress');
        $attempt->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->actingAs($owner)->get(route('member.tryouts.history'))->assertOk()->assertSee('1 hasil tersimpan');
        $this->assertDatabaseHas('tryout_attempts', ['id' => $attempt->id, 'status' => 'timed_out']);
    }
}
