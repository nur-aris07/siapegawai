<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_version_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_version_id')->constrained('question_versions')->restrictOnDelete();
            $table->string('slot', 20);
            $table->unsignedSmallInteger('position');
            $table->string('object_key', 220)->unique();
            $table->char('sha256', 64);
            $table->string('mime_type', 32);
            $table->unsignedInteger('byte_size');
            $table->unsignedSmallInteger('width_px');
            $table->unsignedSmallInteger('height_px');
            $table->string('alt_text', 300)->nullable();
            $table->string('caption', 300)->nullable();
            $table->timestamps();
            $table->unique(['question_version_id', 'slot', 'position'], 'qvm_version_slot_position_unique');
        });
    }

    public function down(): void
    {
        // Rollback hanya untuk lingkungan disposable tanpa referensi media.
        // Jangan jalankan migrate:rollback pada target yang pernah menyimpan media.
        if (Schema::hasTable('question_version_media') && \Illuminate\Support\Facades\DB::table('question_version_media')->exists()) {
            throw new \RuntimeException('Menolak rollback: terdapat referensi media historis. Gunakan controlled forward-fix.');
        }
        Schema::dropIfExists('question_version_media');
    }
};
