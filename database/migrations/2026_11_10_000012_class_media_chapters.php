<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('class_chapters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->string('title', 160);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['class_id', 'sort_order']);
        });
        Schema::table('learning_materials', function (Blueprint $table): void {
            $table->foreignId('chapter_id')->nullable()->after('class_id')->constrained('class_chapters')->restrictOnDelete();
            $table->index(['class_id', 'chapter_id', 'status']);
        });
        Schema::create('learning_material_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained('learning_materials')->restrictOnDelete();
            $table->string('kind', 20); // pdf | video_link
            $table->string('title', 160);
            $table->string('private_path', 255)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('original_name', 160)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->timestamps();
            $table->index(['material_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_material_media');
        Schema::table('learning_materials', function (Blueprint $table): void {
            $table->dropForeign(['chapter_id']);
            $table->dropIndex(['class_id', 'chapter_id', 'status']);
            $table->dropColumn('chapter_id');
        });
        Schema::dropIfExists('class_chapters');
    }
};
