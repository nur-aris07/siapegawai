<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningMaterialMedia extends Model
{
    protected $table = 'learning_material_media';
    protected $fillable = ['material_id', 'kind', 'title', 'private_path', 'external_url', 'original_name', 'size_bytes', 'sha256'];
    protected function casts(): array { return ['size_bytes' => 'integer']; }
    public function material(): BelongsTo { return $this->belongsTo(LearningMaterial::class, 'material_id'); }
}
