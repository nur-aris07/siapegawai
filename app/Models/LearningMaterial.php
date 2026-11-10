<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningMaterial extends Model
{
    protected $table = 'learning_materials';
    protected $fillable = ['class_id','chapter_id','title','content','status','sort_order','published_at','published_version_number'];

    protected function casts(): array
    {
        return ['published_at'=>'datetime','sort_order'=>'integer','chapter_id'=>'integer','published_version_number'=>'integer'];
    }

    public function learningClass(): BelongsTo
    {
        return $this->belongsTo(LearningClass::class,'class_id');
    }

    public function chapter(): BelongsTo { return $this->belongsTo(ClassChapter::class, 'chapter_id'); }
    public function mediaAssets(): HasMany { return $this->hasMany(LearningMaterialMedia::class, 'material_id'); }

    public function versions(): HasMany { return $this->hasMany(LearningMaterialVersion::class, 'material_id'); }

    public function completions(): HasMany
    {
        return $this->hasMany(LearningMaterialProgress::class, 'material_id');
    }
}
