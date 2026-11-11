<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qq_quota_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 160);
            $table->string('scope', 16); // base = code FREE; grant = explicit entitlement
            $table->unsignedInteger('daily_limit')->nullable(); // null means unconfigured unless unlimited=true
            $table->boolean('unlimited')->default(false);
            $table->string('status', 16)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['scope', 'status']);
        });
        Schema::create('qq_daily_quota_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('bucket_date_wib');
            $table->unsignedInteger('consumed_count')->default(0);
            $table->timestamps();
            $table->unique(['user_id','bucket_date_wib'], 'qq_daily_bucket_unique');
        });
        // No qq_attempts table until QQ-CORE-02. Attempt ref and idempotency key
        // are unique now, then CORE-02 must add an FK to its own attempt table.
        Schema::create('qq_quota_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('qq_quota_policy_id')->constrained('qq_quota_policies')->restrictOnDelete();
            $table->date('bucket_date_wib');
            $table->string('idempotency_key', 80);
            $table->string('attempt_ref', 100)->unique();
            $table->unsignedInteger('limit_snapshot')->nullable();
            $table->boolean('unlimited_snapshot');
            $table->timestamp('created_at');
            $table->unique(['user_id','idempotency_key'], 'qq_usage_idempotency_unique');
            $table->index(['user_id','bucket_date_wib']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('qq_quota_usages');
        Schema::dropIfExists('qq_daily_quota_counters');
        Schema::dropIfExists('qq_quota_policies');
    }
};
