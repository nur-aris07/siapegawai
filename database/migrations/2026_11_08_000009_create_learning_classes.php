<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 150)->unique();
            $table->string('title', 160);
            $table->text('description');
            $table->text('syllabus')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('capacity')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->string('title', 160);
            $table->longText('content');
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->index(['class_id','status','sort_order']);
        });
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('revoke_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['class_id','user_id']);
            $table->index(['user_id','status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('learning_materials');
        Schema::dropIfExists('classes');
    }
};
