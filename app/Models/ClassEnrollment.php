<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassEnrollment extends Model
{
    protected $table = 'enrollments';
    protected $fillable = ['class_id','user_id','status','approved_by','approved_at','revoke_reason'];
    protected function casts(): array { return ['approved_at'=>'datetime']; }
    public function learningClass(): BelongsTo { return $this->belongsTo(LearningClass::class, 'class_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
