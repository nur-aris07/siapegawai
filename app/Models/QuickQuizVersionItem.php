<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickQuizVersionItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['quick_quiz_version_id','question_version_id','sort_order','subject_code_snapshot','subject_name_snapshot','topic_name_snapshot'];
    public function version(): BelongsTo { return $this->belongsTo(QuestionVersion::class,'question_version_id'); }
}
