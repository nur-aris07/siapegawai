<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 120);
            $table->string('status', 16)->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 150);
            $table->string('status', 16)->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['subject_id', 'slug']);
        });
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('topics')->restrictOnDelete();
            $table->string('code', 48)->unique();
            $table->foreignId('current_version_id')->nullable();
            $table->string('status', 18)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['topic_id', 'status']);
        });
        Schema::create('question_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('body');
            $table->text('explanation')->nullable();
            $table->string('review_status', 20)->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->char('version_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['question_id', 'version_number']);
        });
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_version_id')->constrained('question_versions')->restrictOnDelete();
            $table->string('label', 1);
            $table->text('body');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('score_weight')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['question_version_id', 'label']);
            $table->unique(['question_version_id', 'sort_order']);
        });
        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('question_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('question_versions');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('subjects');
    }
};
