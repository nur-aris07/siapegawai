<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickQuizAttemptAnswer extends Model
{
    public $timestamps = false;
    protected $fillable = ['quick_quiz_attempt_id','quick_quiz_version_item_id','answer_label','status','is_correct','response_ms','locked_at'];
    protected function casts(): array { return ['is_correct'=>'boolean','response_ms'=>'integer','locked_at'=>'datetime']; }
    public function attempt(): BelongsTo { return $this->belongsTo(QuickQuizAttempt::class,'quick_quiz_attempt_id'); }
    public function item(): BelongsTo { return $this->belongsTo(QuickQuizVersionItem::class,'quick_quiz_version_item_id'); }
}
