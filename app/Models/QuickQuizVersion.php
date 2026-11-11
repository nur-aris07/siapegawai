<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuickQuizVersion extends Model
{
    protected $fillable = ['quick_quiz_id','version_number','status','title','description','seconds_per_question','published_at','published_by'];
    protected function casts(): array { return ['published_at'=>'datetime', 'seconds_per_question'=>'integer']; }
    public function quiz(): BelongsTo { return $this->belongsTo(QuickQuiz::class,'quick_quiz_id'); }
    public function items(): HasMany { return $this->hasMany(QuickQuizVersionItem::class)->orderBy('sort_order'); }
}
