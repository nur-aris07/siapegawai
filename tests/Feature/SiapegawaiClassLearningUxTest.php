<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiClassLearningUxTest extends TestCase
{
    use RefreshDatabase;

    private function setupClass(): array
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'participant', 'status' => 'active']);
        $class = LearningClass::query()->create([
            'title' => 'Kelas Visual Uji',
            'slug' => 'kelas-visual-uji',
            'description' => 'Kelas pengujian tampilan pembelajaran.',
            'syllabus' => "## Tujuan belajar\n\n- Materi pertama\n- Materi kedua",
            'status' => 'open',
            'created_by' => $admin->id,
        ]);
        ClassEnrollment::query()->create([
            'class_id' => $class->id,
            'user_id' => $member->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'status' => 'active',
        ]);
        return [$member, $class];
    }

    public function test_class_discloses_each_material_separately_and_keeps_practice_and_progress(): void
    {
        [$member, $class] = $this->setupClass();
        foreach ([1, 2] as $i) {
            $class->materials()->create([
                'title' => 'Materi Uji '.$i,
                'content' => "## Pengantar $i\n\n- Konsep satu\n- Konsep dua",
                'sort_order' => $i,
                'status' => 'published',
                'published_at' => now(),
            ]);
        }
        $html = $this->actingAs($member)->get(route('member.classes.show', $class))
            ->assertOk()->assertSee('Materi belajar')->assertSee('Latihan Kelas')
            ->assertSee('0 dari 2 materi selesai')->getContent();
        $this->assertSame(2, substr_count($html, 'class="class-course-lesson"'));
        $this->assertSame(2, substr_count($html, 'name="class-course-lessons"'));
        $this->assertStringNotContainsString('class="class-course-lesson" open', $html);
        $this->assertStringContainsString(route('member.class-practices.index', $class), $html);
        $this->assertStringContainsString('Semua kelas', $html);
        $this->assertStringContainsString('<h2>Pengantar 1</h2>', $html);
        $this->assertStringContainsString('<li>Konsep satu</li>', $html);
    }

    public function test_material_markup_escapes_raw_html_and_unsafe_links(): void
    {
        [$member, $class] = $this->setupClass();
        $class->materials()->create([
            'title' => 'Keamanan Konten',
            'content' => "## Aman\n\n<script>alert(1)</script>\n\n[Klik](javascript:alert(1))",
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $html = $this->actingAs($member)->get(route('member.classes.show', $class))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_unenrolled_and_revoked_participants_cannot_read_restructured_materials(): void
    {
        [$member, $class] = $this->setupClass();
        $other = User::factory()->create(['role' => 'participant', 'status' => 'active']);
        $this->actingAs($other)->get(route('member.classes.show', $class))->assertNotFound();
        ClassEnrollment::query()->where('class_id', $class->id)->where('user_id', $member->id)
            ->update(['status' => 'revoked']);
        $this->actingAs($member)->get(route('member.classes.show', $class))->assertNotFound();
    }

    public function test_dashboard_uses_semantic_icons_with_text_for_learning_tasks(): void
    {
        [$member, $class] = $this->setupClass();
        $html = $this->actingAs($member)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertSame(4, substr_count($html, 'class="participant-task-icon"'));
        foreach (['Tryout saya', 'Kelas saya', 'Quiz Kilat', 'Analisis Kemampuan'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public function test_hub_uses_destination_links_not_false_back_navigation(): void
    {
        [$member, $class] = $this->setupClass();
        $hub = $this->actingAs($member)->get(route('member.analytics.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('← Ringkasan peserta', $hub);
        $this->assertStringContainsString('Riwayat &amp; analisis Tryout', $hub);
        $this->assertStringContainsString('Buka Kelas saya', $hub);
        $this->assertStringContainsString('Analisis Quiz Kilat', $hub);
        $this->get(route('member.classes.index'))->assertOk()->assertSee('Lihat Analisis Kemampuan');
    }

    public function test_class_dom_order_matches_learning_priority_on_mobile_without_duplication(): void
    {
        [$member, $class] = $this->setupClass();
        $material = $class->materials()->create([
            'title' => 'Materi bertingkat dengan judul yang cukup panjang untuk layar ponsel kecil',
            'content' => "# Dasar Materi\n\n## Subbab 1\n\n### Rincian\n\n1. Langkah pertama\n2. Langkah kedua\n\n- Hal penting",
            'sort_order' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $html = $this->actingAs($member)->get(route('member.classes.show', $class))->assertOk()->getContent();
        $progress = strpos($html, 'class="class-course-progress"');
        $materials = strpos($html, 'class="class-course-materials"');
        $practice = strpos($html, 'class="class-course-practice"');
        $syllabus = strpos($html, 'class="class-course-syllabus"');
        $this->assertNotFalse($progress);
        $this->assertNotFalse($materials);
        $this->assertNotFalse($practice);
        $this->assertNotFalse($syllabus);
        $this->assertTrue($progress < $materials && $materials < $practice && $practice < $syllabus);
        foreach (['class="class-course-progress"', 'class="class-course-materials"', 'class="class-course-practice"', 'class="class-course-syllabus"'] as $one) {
            $this->assertSame(1, substr_count($html, $one), $one);
        }
        $this->assertStringContainsString('aria-controls="materi-'.$material->id.'-konten"', $html);
        $this->assertStringContainsString('id="materi-'.$material->id.'-konten"', $html);
        $this->assertStringContainsString('aria-controls="silabus-kelas-konten"', $html);
        $this->assertStringContainsString('id="silabus-kelas-konten"', $html);
        $this->assertStringContainsString('<h2>Subbab 1</h2>', $html);
        $this->assertStringContainsString('<h3>Rincian</h3>', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<ul>', $html);
    }
}
