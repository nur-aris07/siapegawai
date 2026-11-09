<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionVersion extends Model
{
    protected $fillable = ['question_id', 'version_number', 'body', 'explanation', 'review_status', 'reviewed_by', 'reviewed_at', 'published_at', 'version_hash'];
    protected function casts(): array { return ['reviewed_at' => 'datetime', 'published_at' => 'datetime']; }
    public function question(): BelongsTo { return $this->belongsTo(Question::class); }
    public function options(): HasMany { return $this->hasMany(QuestionOption::class)->orderBy('sort_order'); }
}
