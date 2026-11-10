<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('class_practices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('learning_materials')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['class_id', 'status', 'sort_order'], 'class_practices_list_index');
        });
        Schema::create('class_practice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_practice_id')->constrained('class_practices')->restrictOnDelete();
            $table->foreignId('question_version_id')->constrained('question_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignId('subject_id_snapshot')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('subject_code_snapshot', 12)->nullable();
            $table->string('subject_name_snapshot', 120)->nullable();
            $table->foreignId('topic_id_snapshot')->nullable()->constrained('topics')->nullOnDelete();
            $table->string('topic_name_snapshot', 150)->nullable();
            $table->timestamps();
            $table->unique(['class_practice_id', 'question_version_id'], 'class_practice_item_version_unique');
            $table->unique(['class_practice_id', 'sort_order'], 'class_practice_item_order_unique');
        });
        Schema::create('class_practice_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_practice_id')->constrained('class_practices')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 20)->default('in_progress');
            $table->dateTime('started_at');
            $table->dateTime('expires_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedSmallInteger('answered_count')->nullable();
            $table->unsignedSmallInteger('correct_count')->nullable();
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['class_practice_id', 'user_id', 'attempt_number'], 'class_practice_attempt_number_unique');
            $table->index(['user_id', 'status', 'class_practice_id'], 'class_practice_attempt_member_index');
        });
        Schema::create('class_practice_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_practice_attempt_id')->constrained('class_practice_attempts')->restrictOnDelete();
            $table->foreignId('class_practice_item_id')->constrained('class_practice_items')->restrictOnDelete();
            $table->string('answer_label', 1)->nullable();
            $table->boolean('flagged')->default(false);
            $table->timestamps();
            $table->unique(['class_practice_attempt_id', 'class_practice_item_id'], 'class_practice_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_practice_answers');
        Schema::dropIfExists('class_practice_attempts');
        Schema::dropIfExists('class_practice_items');
        Schema::dropIfExists('class_practices');
    }
};
