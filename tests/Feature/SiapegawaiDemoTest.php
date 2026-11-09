<?php

namespace Tests\Feature;

use App\Services\SampleQuestionBank;
use Tests\TestCase;

class SiapegawaiDemoTest extends TestCase
{
    public function test_public_bank_has_12_samples(): void
    {
        $this->assertCount(12, app(SampleQuestionBank::class)->all());
        $this->get('/bank-soal')->assertOk()->assertSee('12 soal ditemukan');
        $this->get('/bank-soal?kategori=TWK')->assertOk()->assertSee('3 soal ditemukan');
    }

    public function test_guest_can_finish_demo_and_receive_server_calculated_score(): void
    {
        $this->get('/latihan-gratis/kerjakan')->assertOk();
        $answers = [];
        foreach (app(SampleQuestionBank::class)->exam() as $q) $answers[$q['id']] = (string) $q['a'];
        $this->post('/latihan-gratis/hasil', ['answers' => $answers])->assertRedirect('/latihan-gratis/hasil');
        $this->get('/latihan-gratis/hasil')->assertOk()->assertSee('9 / 9');
    }

    public function test_expired_demo_does_not_count_answers(): void
    {
        $answers = [];
        foreach (app(SampleQuestionBank::class)->exam() as $q) $answers[$q['id']] = (string) $q['a'];
        $this->withSession(['demo_started_at' => now()->subMinutes(13)->timestamp])
            ->post('/latihan-gratis/hasil', ['answers' => $answers])->assertRedirect('/latihan-gratis/hasil');
        $this->get('/latihan-gratis/hasil')->assertOk()->assertSee('0 / 9')->assertSee('Waktu 12 menit telah habis.');
    }
}
