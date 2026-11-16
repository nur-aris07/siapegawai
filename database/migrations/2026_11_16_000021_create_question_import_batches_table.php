<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('private_path', 255);
            $table->char('sha256', 64);
            $table->unsignedInteger('byte_count');
            $table->unsignedSmallInteger('row_count')->default(0);
            $table->unsignedSmallInteger('valid_count')->default(0);
            $table->unsignedSmallInteger('invalid_count')->default(0);
            $table->string('status', 16);
            $table->json('errors')->nullable();
            $table->json('result')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
            $table->index(['actor_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        // Conservative rollback: only an unused table may be removed by explicit owner action.
        if (Schema::hasTable('question_import_batches') && \Illuminate\Support\Facades\DB::table('question_import_batches')->exists()) {
            throw new \RuntimeException('Batch import memiliki histori; rollback ditolak agar bukti tidak terhapus.');
        }
        Schema::dropIfExists('question_import_batches');
    }
};
