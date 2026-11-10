<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\ClassPractice;
use App\Models\ClassPracticeAttempt;
use App\Models\LearningClass;
use App\Models\LearningMaterialProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiClassMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'participant', string $status = 'active', ?string $name = null): User
    {
        return User::factory()->create(array_filter([
            'role' => $role, 'status' => $status, 'name' => $name,
        ], fn ($value) => $value !== null));
    }

    private function klass(User $admin): LearningClass
    {
        return LearningClass::query()->create([
            'title' => 'Kelas Monitoring Internal',
            'slug' => 'monitoring-'.fake()->unique()->bothify('????????'),
            'description' => 'Kelas untuk pengujian monitoring dan isolasi riwayat peserta.',
            'status' => 'open', 'created_by' => $admin->id,
        ]);
    }

    private function enroll(LearningClass $class, User $person, User $admin, string $status = 'active'): ClassEnrollment
    {
        return ClassEnrollment::query()->create([
            'class_id' => $class->id, 'user_id' => $person->id,
            'approved_by' => $admin->id, 'approved_at' => now(), 'status' => $status,
            'revoke_reason' => $status === 'revoked' ? 'Akses uji monitoring dicabut' : null,
        ]);
    }

    private function practice(LearningClass $class): ClassPractice
    {
        return ClassPractice::query()->create([
            'class_id' => $class->id, 'title' => 'Latihan Kelas Monitoring',
            'status' => 'published', 'duration_minutes' => 30, 'max_attempts' => 3,
            'published_at' => now(),
        ]);
    }

    private function attempt(ClassPractice $practice, User $member, int $number, string $status, float $score): ClassPracticeAttempt
    {
        return ClassPracticeAttempt::query()->create([
            'class_practice_id' => $practice->id, 'user_id' => $member->id,
            'attempt_number' => $number, 'status' => $status,
            'started_at' => now()->subMinutes(25), 'expires_at' => now()->addMinutes(5),
            'finished_at' => now()->subMinutes(2 - $number),
            'total_questions' => 4, 'answered_count' => 4,
            'correct_count' => (int) round($score * 4 / 100), 'score_percent' => $score,
        ]);
    }

    public function test_monitoring_requires_active_admin_and_blocks_guests_participants_and_inactive_admin(): void
    {
        $admin = $this->person('superadmin');
        $class = $this->klass($admin);
        $url = route('admin.classes.monitoring.index', $class);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->person())->get($url)->assertForbidden();
        $this->actingAs($this->person('operational_admin', 'suspended'))->get($url)->assertForbidden();
        $this->actingAs($this->person('operational_admin'))->get($url)->assertOk();
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Monitoring:');
    }

    public function test_overview_uses_only_published_materials_and_retains_revoked_progress(): void
    {
        $admin = $this->person('superadmin'); $class = $this->klass($admin);
        $first = $class->materials()->create(['title'=>'TWK Terbit','content'=>'Materi yang sudah diterbitkan pada kelas ini.', 'status'=>'published']);
        $class->materials()->create(['title'=>'TIU Terbit','content'=>'Materi lain untuk pengujian progres.', 'status'=>'published']);
        $class->materials()->create(['title'=>'Materi Draft Tersembunyi','content'=>'Draft tidak boleh dihitung pada persentase.', 'status'=>'draft']);
        $active = $this->person('participant', 'active', 'Peserta Aktif Satu');
        $revoked = $this->person('participant', 'active', 'Peserta Akses Dicabut');
        $this->enroll($class, $active, $admin);
        $this->enroll($class, $revoked, $admin, 'revoked');
        LearningMaterialProgress::query()->create(['user_id'=>$active->id,'material_id'=>$first->id,'completed_at'=>now()->subDay()]);
        LearningMaterialProgress::query()->create(['user_id'=>$revoked->id,'material_id'=>$first->id,'completed_at'=>now()->subDay()]);
        $practice = $this->practice($class);
        $this->attempt($practice, $revoked, 1, 'completed', 50);
        $this->actingAs($admin)->get(route('admin.classes.monitoring.index', $class))
            ->assertOk()->assertSee('1/2 (50%)')->assertSee('Peserta Akses Dicabut')
            ->assertSee('Dicabut')->assertSee('50%')->assertDontSee('1/3 (33%)');
        $this->get(route('admin.classes.monitoring.show', [$class, $revoked]))
            ->assertOk()->assertSee('TWK Terbit')->assertSee('TIU Terbit')
            ->assertSee('1 dari 2 (50%)')->assertSee('Latihan Kelas Monitoring')
            ->assertDontSee('Materi Draft Tersembunyi');
        $this->assertSame('revoked', ClassEnrollment::query()->where('user_id', $revoked->id)->value('status'));
        $this->assertDatabaseCount('learning_material_progress', 2);
    }

    public function test_attempts_are_scoped_to_class_member_and_completed_status_only(): void
    {
        $admin=$this->person('superadmin'); $first=$this->klass($admin); $second=$this->klass($admin);
        $member=$this->person(); $other=$this->person();
        $this->enroll($first,$member,$admin,'revoked');
        $main=$this->practice($first); $foreign=$this->practice($second);
        $this->attempt($main,$member,1,'completed',25);
        $this->attempt($main,$member,2,'timed_out',75);
        $this->attempt($foreign,$member,1,'completed',100);
        $this->attempt($main,$other,1,'completed',100);
        ClassPracticeAttempt::query()->create([
            'class_practice_id'=>$main->id,'user_id'=>$member->id,'attempt_number'=>3,
            'status'=>'in_progress','started_at'=>now(),'expires_at'=>now()->addMinutes(30),
            'total_questions'=>4,
        ]);
        $this->actingAs($admin)->get(route('admin.classes.monitoring.index',$first))
            ->assertOk()->assertSee('75,00%');
        $this->get(route('admin.classes.monitoring.show',[$first,$member]))
            ->assertOk()->assertSee('Habis waktu')->assertSee('Dikumpulkan')
            ->assertSee('75,00%')->assertSee('25,00%')->assertDontSee('100,00%');
        // Monitoring is read-only; it must not expire or rescore an active attempt.
        $this->assertDatabaseHas('class_practice_attempts',[
            'class_practice_id'=>$main->id,'user_id'=>$member->id,'attempt_number'=>3,'status'=>'in_progress',
        ]);
    }

    public function test_detail_rejects_foreign_class_non_enrolled_and_admin_accounts(): void
    {
        $admin=$this->person('superadmin'); $class=$this->klass($admin);
        $otherClass=$this->klass($admin); $member=$this->person();
        $this->enroll($otherClass,$member,$admin);
        $this->actingAs($admin)->get(route('admin.classes.monitoring.show',[$class,$member]))->assertNotFound();
        $this->get(route('admin.classes.monitoring.show',[$otherClass,$admin]))->assertNotFound();
        $this->actingAs($member)->get(route('admin.classes.monitoring.show',[$otherClass,$member]))->assertForbidden();
    }

    public function test_filters_validate_and_paginate_without_mixing_other_classes(): void
    {
        $admin=$this->person('superadmin'); $class=$this->klass($admin); $otherClass=$this->klass($admin);
        for ($i=1;$i<=17;$i++) {
            $person=$this->person('participant','active',sprintf('Peserta Monitor %02d',$i));
            $this->enroll($class,$person,$admin, $i===17?'revoked':'active');
        }
        $outsider=$this->person('participant','active','Peserta Luar Kelas');
        $this->enroll($otherClass,$outsider,$admin);
        $this->actingAs($admin)->get(route('admin.classes.monitoring.index',$class))
            ->assertOk()->assertDontSee('Peserta Monitor 17')->assertDontSee('Peserta Luar Kelas');
        $this->get(route('admin.classes.monitoring.index',$class).'?page=2')
            ->assertOk()->assertSee('Peserta Monitor 17');
        $this->get(route('admin.classes.monitoring.index',$class).'?status=revoked')
            ->assertOk()->assertSee('Peserta Monitor 17')->assertDontSee('Peserta Monitor 01');
        $this->get(route('admin.classes.monitoring.index',$class).'?q=Monitor%2002')
            ->assertOk()->assertSee('Peserta Monitor 02')->assertDontSee('Peserta Monitor 01');
        $this->get(route('admin.classes.monitoring.index',$class).'?status=unknown')
            ->assertRedirect()->assertSessionHasErrors('status');
    }
    public function test_monitoring_ajax_uses_yajra_same_route_and_keeps_class_boundaries(): void
    {
        $admin = $this->person('superadmin');
        $class = $this->klass($admin);
        $otherClass = $this->klass($admin);
        $active = $this->person('participant', 'active', 'Monitoring Alfa');
        $revoked = $this->person('participant', 'active', 'Monitoring Beta');
        $outside = $this->person('participant', 'active', 'Monitoring Luar');
        $this->enroll($class, $active, $admin, 'active');
        $this->enroll($class, $revoked, $admin, 'revoked');
        $this->enroll($otherClass, $outside, $admin);
        $url = route('admin.classes.monitoring.index', $class);
        $params = ['draw' => 2, 'start' => 0, 'length' => 15,
            'status' => 'revoked', 'search' => ['value' => 'Beta', 'regex' => 'false']];

        $response = $this->actingAs($admin)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($url.'?'.http_build_query($params))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Monitoring Beta')
            ->assertJsonPath('data.0.enrollment_status', 'revoked')
            ->assertJsonMissing(['name' => 'Monitoring Luar']);
        $this->assertSame(0, (int) $response->json('data.0.progress_percent'));
    }

    public function test_monitoring_ajax_rejects_invalid_filters_and_unauthorized_users(): void
    {
        $admin = $this->person('superadmin');
        $class = $this->klass($admin);
        $url = route('admin.classes.monitoring.index', $class).'?draw=1&start=0&length=15';
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
        $this->actingAs($this->person())->get($url)->assertForbidden();
        $this->actingAs($admin)->get($url.'&status=invalid')->assertUnprocessable();
        $this->actingAs($admin)->get($url.'&search[value]='.str_repeat('x', 110))->assertUnprocessable();
    }

    public function test_monitoring_view_preserves_server_rendered_fallback_and_scoped_ui(): void
    {
        $admin = $this->person('superadmin');
        $class = $this->klass($admin);
        $member = $this->person('participant', 'active', 'Fallback Peserta');
        $this->enroll($class, $member, $admin);
        $this->actingAs($admin)->get(route('admin.classes.monitoring.index', $class))
            ->assertOk()
            ->assertSee('class="admin-monitoring"', false)
            ->assertSee('data-monitoring-table', false)
            ->assertSee('Fallback Peserta')
            ->assertSee('monitoring-rows');
        $this->get(route('admin.classes.monitoring.show', [$class, $member]))
            ->assertOk()->assertSee('class="admin-monitoring"', false);
    }

}
