<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tryout_package_items', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id_snapshot')->nullable();
            $table->string('subject_code_snapshot', 12)->nullable();
            $table->string('subject_name_snapshot', 120)->nullable();
            $table->unsignedBigInteger('topic_id_snapshot')->nullable();
            $table->string('topic_name_snapshot', 150)->nullable();
        });

        // Backfill pre-v1.0 items with the *currently available* taxonomy.
        // Historical classification changed before this migration cannot be reconstructed.
        $lastId = 0;
        do {
            $rows = DB::table('tryout_package_items as items')
                ->join('question_versions as versions', 'versions.id', '=', 'items.question_version_id')
                ->join('questions as questions', 'questions.id', '=', 'versions.question_id')
                ->join('topics as topics', 'topics.id', '=', 'questions.topic_id')
                ->join('subjects as subjects', 'subjects.id', '=', 'topics.subject_id')
                ->where('items.id', '>', $lastId)
                ->orderBy('items.id')->limit(200)
                ->get([
                    'items.id as id', 'subjects.id as subject_id', 'subjects.code as subject_code',
                    'subjects.name as subject_name', 'topics.id as topic_id', 'topics.name as topic_name',
                ]);
            foreach ($rows as $row) {
                DB::table('tryout_package_items')->where('id', $row->id)->update([
                    'subject_id_snapshot' => $row->subject_id,
                    'subject_code_snapshot' => $row->subject_code,
                    'subject_name_snapshot' => $row->subject_name,
                    'topic_id_snapshot' => $row->topic_id,
                    'topic_name_snapshot' => $row->topic_name,
                ]);
                $lastId = (int) $row->id;
            }
        } while ($rows->count() === 200);
    }

    public function down(): void
    {
        Schema::table('tryout_package_items', function (Blueprint $table) {
            $table->dropColumn([
                'subject_id_snapshot', 'subject_code_snapshot', 'subject_name_snapshot',
                'topic_id_snapshot', 'topic_name_snapshot',
            ]);
        });
    }
};
