<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickQuizDailyQuotaCounter extends Model
{
    protected $table = 'qq_daily_quota_counters';
    protected $fillable = ['user_id','bucket_date_wib','consumed_count'];
    protected function casts(): array { return ['consumed_count'=>'integer']; }
}
