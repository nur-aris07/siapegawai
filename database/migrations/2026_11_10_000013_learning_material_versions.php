<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('learning_materials', function (Blueprint $table): void {
            $table->unsignedInteger('published_version_number')->default(0);
        });
        Schema::table('learning_material_progress', function (Blueprint $table): void {
            $table->unsignedInteger('completed_version_number')->nullable();
        });
        Schema::create('learning_material_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained('learning_materials')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16); // draft | published
            $table->string('title', 160);
            $table->longText('content');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('chapter_id')->nullable()->constrained('class_chapters')->restrictOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['material_id', 'version_number']);
            $table->index(['material_id', 'status']);
        });

        // Backfill once for all already-published materials; never change existing content.
        DB::table('learning_materials')->where('status', 'published')->orderBy('id')
            ->chunkById(200, function ($materials): void {
                $timestamp = now();
                $snapshots = [];
                foreach ($materials as $material) {
                    $snapshots[] = [
                        'material_id' => $material->id, 'version_number' => 1, 'status' => 'published',
                        'title' => $material->title, 'content' => $material->content,
                        'sort_order' => $material->sort_order, 'chapter_id' => $material->chapter_id,
                        'published_at' => $material->published_at ?? $timestamp,
                        'created_at' => $timestamp, 'updated_at' => $timestamp,
                    ];
                }
                DB::table('learning_material_versions')->insert($snapshots);
                $ids = $materials->pluck('id')->all();
                DB::table('learning_materials')->whereIn('id', $ids)
                    ->update(['published_version_number' => 1]);
                DB::table('learning_material_progress')->whereIn('material_id', $ids)
                    ->whereNull('completed_version_number')->update(['completed_version_number' => 1]);
            });
    }

    public function down(): void
    {
        // This is destructive to revision history. Never run rollback on a live database.
        Schema::dropIfExists('learning_material_versions');
        Schema::table('learning_material_progress', fn (Blueprint $table) => $table->dropColumn('completed_version_number'));
        Schema::table('learning_materials', fn (Blueprint $table) => $table->dropColumn('published_version_number'));
    }
};
