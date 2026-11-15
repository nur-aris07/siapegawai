<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('member_plans', function (Blueprint $table): void {
            $table->string('availability', 16)->default('planned');
            $table->boolean('is_archived')->default(false);
            $table->unsignedBigInteger('published_version_id')->nullable();
        });
        Schema::create('member_plan_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_plan_id')->constrained('member_plans')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('title', 120);
            $table->text('summary');
            $table->json('benefits');
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['member_plan_id', 'version_no'], 'member_plan_version_unique');
            $table->index(['member_plan_id', 'status']);
        });
        Schema::create('package_upgrade_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('from_member_plan_id')->constrained('member_plans')->restrictOnDelete();
            $table->foreignId('target_member_plan_id')->constrained('member_plans')->restrictOnDelete();
            $table->foreignId('target_published_version_id')->constrained('member_plan_versions')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('admin_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
        // Preserve IDs, grants, policies and usage. Backfill only missing published snapshots.
        // Text below is transcribed from the original public /paket page, not invented pricing.
        $initial = [
            'PEJUANG' => ['Pejuang · gratis', 'Mulai dari 9 soal latihan SKD dengan timer dan pembahasan sementara setelah pengerjaan. Riwayat akun belum tersedia untuk demo.', 'requestable'],
            'ABDI_NEGARA' => ['Abdi Negara', 'Rencana akses latihan lebih luas, analisis materi, dan pembahasan terjadwal.', 'planned'],
            'VIP' => ['VIP Mentoring', 'Rencana pendampingan tutor, sesi kelompok, dan peninjauan progres berkala.', 'planned'],
        ];
        foreach ($initial as $code => [$title, $summary, $availability]) {
            $plan = DB::table('member_plans')->where('code', $code)->first();
            if (!$plan) throw new \RuntimeException('Missing canonical plan: '.$code);
            DB::table('member_plans')->where('id', $plan->id)->update(['availability' => $availability]);
            if ($plan->published_version_id) continue;
            $existing = DB::table('member_plan_versions')->where('member_plan_id', $plan->id)->where('version_no', 1)->first();
            $id = $existing?->id ?? DB::table('member_plan_versions')->insertGetId([
                'member_plan_id' => $plan->id, 'version_no' => 1, 'title' => $title,
                'summary' => $summary, 'benefits' => json_encode([], JSON_THROW_ON_ERROR),
                'status' => 'published', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('member_plans')->where('id', $plan->id)->update(['published_version_id' => $id]);
        }
    }

    public function down(): void
    {
        // Never automatically discard snapshots/upgrade history on a populated installation.
        if (DB::table('package_upgrade_requests')->exists() || DB::table('member_plan_versions')->where('version_no', '>', 1)->exists()
            || DB::table('member_plans')->whereNotIn('code', ['PEJUANG', 'ABDI_NEGARA', 'VIP'])->exists()) {
            throw new \RuntimeException('PKG-CATALOG rollback contains business data: restore verified DB backup instead.');
        }
        Schema::dropIfExists('package_upgrade_requests');
        Schema::dropIfExists('member_plan_versions');
        Schema::table('member_plans', fn (Blueprint $table) => $table->dropColumn(['availability','is_archived','published_version_id']));
    }
};
