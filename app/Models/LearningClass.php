<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningClass extends Model
{
    protected $table = 'classes';
    protected $fillable = ['slug', 'title', 'description', 'syllabus', 'status', 'capacity', 'created_by'];
    protected function casts(): array { return ['capacity' => 'integer']; }
    public function materials(): HasMany { return $this->hasMany(LearningMaterial::class, 'class_id'); }
    public function enrollments(): HasMany { return $this->hasMany(ClassEnrollment::class, 'class_id'); }
}
