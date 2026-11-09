<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learning_material_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('learning_materials')->restrictOnDelete();
            $table->dateTime('completed_at');
            $table->timestamps();
            $table->unique(['user_id', 'material_id']);
            $table->index(['material_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_material_progress');
    }
};
