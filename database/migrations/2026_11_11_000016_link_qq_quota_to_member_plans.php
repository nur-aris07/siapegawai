<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 80);
            $table->boolean('is_paid');
            $table->unsignedTinyInteger('tier');
            $table->timestamps();
        });
        // Only plan identities are predefined; NO quota amounts, prices or paid memberships.
        $now = now();
        DB::table('member_plans')->insert([
            ['code'=>'PEJUANG','name'=>'Pejuang','is_paid'=>false,'tier'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'ABDI_NEGARA','name'=>'Abdi Negara','is_paid'=>true,'tier'=>2,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'VIP','name'=>'VIP','is_paid'=>true,'tier'=>3,'created_at'=>$now,'updated_at'=>$now],
        ]);
        Schema::table('qq_quota_policies', function (Blueprint $table): void {
            // Legacy FREE/qq_quota policy and usage rows remain intact for historical integrity.
            // Only explicit plan-bound policies are eligible going forward.
            $table->foreignId('member_plan_id')->nullable()->after('id')
                ->constrained('member_plans')->restrictOnDelete();
            $table->unique('member_plan_id', 'qq_one_policy_per_member_plan');
        });
    }

    public function down(): void
    {
        Schema::table('qq_quota_policies', function (Blueprint $table): void {
            $table->dropUnique('qq_one_policy_per_member_plan');
            $table->dropConstrainedForeignId('member_plan_id');
        });
        Schema::dropIfExists('member_plans');
    }
};
