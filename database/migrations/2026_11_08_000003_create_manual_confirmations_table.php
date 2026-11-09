<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context_type', 40)->default('registration');
            $table->string('confirmation_type', 40)->default('whatsapp');
            $table->string('external_reference', 120)->nullable();
            $table->string('decision', 20);
            $table->text('note');
            $table->foreignId('confirmed_by')->constrained('users');
            $table->timestamp('confirmed_at');
            $table->timestamps();
            $table->index(['user_id', 'context_type']);
        });
    }
    public function down(): void { Schema::dropIfExists('manual_confirmations'); }
};
