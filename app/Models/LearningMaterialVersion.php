<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningMaterialVersion extends Model
{
    protected $table = 'learning_material_versions';
    protected $fillable = [
        'material_id', 'version_number', 'status', 'title', 'content',
        'sort_order', 'chapter_id', 'published_at', 'published_by',
    ];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'sort_order' => 'integer',
            'chapter_id' => 'integer', 'published_at' => 'datetime'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(LearningMaterial::class, 'material_id');
    }
}
