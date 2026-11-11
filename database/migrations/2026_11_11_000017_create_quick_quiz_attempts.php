<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quick_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('quick_quiz_version_id')->constrained('quick_quiz_versions')->restrictOnDelete();
            $table->foreignId('qq_quota_usage_id')->nullable()->unique()->constrained('qq_quota_usages')->restrictOnDelete();
            $table->string('start_key', 80);
            $table->string('status', 16)->default('active'); // active, completed, abandoned
            $table->string('phase', 16)->default('question'); // question, feedback, finished
            $table->unsignedSmallInteger('current_index')->default(0);
            $table->unsignedSmallInteger('total_questions');
            $table->dateTime('question_started_at');
            $table->dateTime('question_expires_at');
            $table->dateTime('feedback_until')->nullable();
            $table->dateTime('last_seen_at');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->decimal('score_percent', 6, 2)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'start_key'], 'qq_attempt_start_unique');
            $table->index(['user_id', 'status', 'id']);
        });
        Schema::create('quick_quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quick_quiz_attempt_id')->constrained('quick_quiz_attempts')->restrictOnDelete();
            $table->foreignId('quick_quiz_version_item_id')->constrained('quick_quiz_version_items')->restrictOnDelete();
            $table->string('answer_label', 5)->nullable();
            $table->string('status', 16); // answered, timeout
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('response_ms');
            $table->dateTime('locked_at');
            $table->unique(['quick_quiz_attempt_id', 'quick_quiz_version_item_id'], 'qq_attempt_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_quiz_attempt_answers');
        Schema::dropIfExists('quick_quiz_attempts');
    }
};
