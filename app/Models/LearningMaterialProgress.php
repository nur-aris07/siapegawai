<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningMaterialProgress extends Model
{
    protected $table = 'learning_material_progress';

    protected $fillable = ['user_id', 'material_id', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'completed_version_number' => 'integer'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(LearningMaterial::class, 'material_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
