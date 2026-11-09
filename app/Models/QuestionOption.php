<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $fillable = ['question_version_id', 'label', 'body', 'is_correct', 'score_weight', 'sort_order'];
    protected function casts(): array { return ['is_correct' => 'boolean']; }
    public function version(): BelongsTo { return $this->belongsTo(QuestionVersion::class, 'question_version_id'); }
}
