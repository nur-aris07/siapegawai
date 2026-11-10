<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassPracticeAttempt extends Model
{
    protected $fillable = ['class_practice_id', 'user_id', 'attempt_number', 'status', 'started_at', 'expires_at', 'finished_at', 'total_questions', 'answered_count', 'correct_count', 'score_percent'];
    protected function casts(): array
    {
        return ['started_at'=>'datetime', 'expires_at'=>'datetime', 'finished_at'=>'datetime', 'attempt_number'=>'integer', 'total_questions'=>'integer', 'answered_count'=>'integer', 'correct_count'=>'integer', 'score_percent'=>'decimal:2'];
    }
    public function practice(): BelongsTo { return $this->belongsTo(ClassPractice::class,'class_practice_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function answers(): HasMany { return $this->hasMany(ClassPracticeAnswer::class); }
}
