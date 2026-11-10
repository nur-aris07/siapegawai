<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassChapter extends Model
{
    protected $fillable = ['class_id', 'title', 'sort_order'];
    protected function casts(): array { return ['sort_order' => 'integer']; }
    public function learningClass(): BelongsTo { return $this->belongsTo(LearningClass::class, 'class_id'); }
    public function materials(): HasMany { return $this->hasMany(LearningMaterial::class, 'chapter_id'); }
}
