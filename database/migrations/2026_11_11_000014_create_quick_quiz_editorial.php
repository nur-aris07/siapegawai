<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quick_quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('status', 16)->default('draft');
            $table->foreignId('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
        Schema::create('quick_quiz_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quick_quiz_id')->constrained('quick_quizzes')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16)->default('draft');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('seconds_per_question');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['quick_quiz_id', 'version_number'], 'qq_version_number_unique');
            $table->index(['quick_quiz_id', 'status']);
        });
        Schema::create('quick_quiz_version_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quick_quiz_version_id')->constrained('quick_quiz_versions')->restrictOnDelete();
            $table->foreignId('question_version_id')->constrained('question_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('subject_code_snapshot', 12)->nullable();
            $table->string('subject_name_snapshot', 120)->nullable();
            $table->string('topic_name_snapshot', 150)->nullable();
            $table->unique(['quick_quiz_version_id', 'question_version_id'], 'qq_item_version_unique');
            $table->unique(['quick_quiz_version_id', 'sort_order'], 'qq_item_order_unique');
        });
        Schema::table('quick_quizzes', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('quick_quiz_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quick_quizzes', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('quick_quiz_version_items');
        Schema::dropIfExists('quick_quiz_versions');
        Schema::dropIfExists('quick_quizzes');
    }
};
