<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tryout_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('tryout_package_id')->constrained('tryout_packages')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 16)->default('in_progress');
            $table->dateTime('started_at');
            $table->dateTime('expires_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedSmallInteger('answered_count')->nullable();
            $table->unsignedSmallInteger('correct_count')->nullable();
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['user_id','tryout_package_id','attempt_number'], 'tryout_attempt_unique_number');
            $table->index(['user_id','status']);
        });
        Schema::create('tryout_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tryout_attempt_id')->constrained('tryout_attempts')->cascadeOnDelete();
            $table->foreignId('tryout_package_item_id')->constrained('tryout_package_items')->restrictOnDelete();
            $table->string('answer_label', 1)->nullable();
            $table->boolean('flagged')->default(false);
            $table->timestamps();
            $table->unique(['tryout_attempt_id','tryout_package_item_id'], 'tryout_answer_unique_item');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tryout_attempt_answers');
        Schema::dropIfExists('tryout_attempts');
    }
};
