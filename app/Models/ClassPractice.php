<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassPractice extends Model
{
    protected $fillable = ['class_id', 'material_id', 'title', 'description', 'duration_minutes', 'max_attempts', 'sort_order', 'status', 'published_at', 'archived_at', 'created_by'];
    protected function casts(): array
    {
        return ['duration_minutes'=>'integer','max_attempts'=>'integer','sort_order'=>'integer','published_at'=>'datetime','archived_at'=>'datetime'];
    }
    public function learningClass(): BelongsTo { return $this->belongsTo(LearningClass::class, 'class_id'); }
    public function material(): BelongsTo { return $this->belongsTo(LearningMaterial::class, 'material_id'); }
    public function items(): HasMany { return $this->hasMany(ClassPracticeItem::class)->orderBy('sort_order'); }
    public function attempts(): HasMany { return $this->hasMany(ClassPracticeAttempt::class); }
}
