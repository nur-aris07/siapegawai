<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_subject', 255);
            $table->timestamps();
            $table->unique(['provider', 'provider_subject'], 'identities_provider_subject_unique');
            $table->unique(['user_id', 'provider'], 'identities_user_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identities');
    }
};
