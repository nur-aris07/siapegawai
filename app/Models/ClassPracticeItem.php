<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassPracticeItem extends Model
{
    protected $fillable = ['class_practice_id', 'question_version_id', 'sort_order', 'subject_id_snapshot', 'subject_code_snapshot', 'subject_name_snapshot', 'topic_id_snapshot', 'topic_name_snapshot'];
    public function practice(): BelongsTo { return $this->belongsTo(ClassPractice::class,'class_practice_id'); }
    public function version(): BelongsTo { return $this->belongsTo(QuestionVersion::class,'question_version_id'); }
}
