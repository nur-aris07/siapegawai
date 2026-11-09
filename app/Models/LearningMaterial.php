<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningMaterial extends Model
{
    protected $table = 'learning_materials';
    protected $fillable = ['class_id','title','content','status','sort_order','published_at'];

    protected function casts(): array
    {
        return ['published_at'=>'datetime','sort_order'=>'integer'];
    }

    public function learningClass(): BelongsTo
    {
        return $this->belongsTo(LearningClass::class,'class_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LearningMaterialProgress::class, 'material_id');
    }
}
