<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tryout_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->string('status', 18)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('tryout_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tryout_package_id')->constrained('tryout_packages')->restrictOnDelete();
            // Referensi versi, bukan versi terkini dari master soal.
            $table->foreignId('question_version_id')->constrained('question_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['tryout_package_id', 'question_version_id'], 'tryout_items_version_unique');
            $table->unique(['tryout_package_id', 'sort_order'], 'tryout_items_order_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tryout_package_items');
        Schema::dropIfExists('tryout_packages');
    }
};
