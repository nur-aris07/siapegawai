<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassPracticeAnswer extends Model
{
    protected $fillable = ['class_practice_attempt_id', 'class_practice_item_id', 'answer_label', 'flagged'];
    protected function casts(): array { return ['flagged'=>'boolean']; }
    public function attempt(): BelongsTo { return $this->belongsTo(ClassPracticeAttempt::class,'class_practice_attempt_id'); }
    public function item(): BelongsTo { return $this->belongsTo(ClassPracticeItem::class,'class_practice_item_id'); }
}
