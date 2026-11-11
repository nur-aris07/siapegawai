<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuickQuizAttempt extends Model
{
    protected $fillable = ['user_id','quick_quiz_version_id','qq_quota_usage_id','start_key','status','phase','current_index','total_questions','question_started_at','question_expires_at','feedback_until','last_seen_at','started_at','finished_at','correct_count','score_percent'];
    protected function casts(): array
    {
        return ['question_started_at'=>'datetime','question_expires_at'=>'datetime','feedback_until'=>'datetime',
            'last_seen_at'=>'datetime','started_at'=>'datetime','finished_at'=>'datetime',
            'current_index'=>'integer','total_questions'=>'integer','correct_count'=>'integer','score_percent'=>'decimal:2'];
    }
    public function version(): BelongsTo { return $this->belongsTo(QuickQuizVersion::class,'quick_quiz_version_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function answers(): HasMany { return $this->hasMany(QuickQuizAttemptAnswer::class); }
}
