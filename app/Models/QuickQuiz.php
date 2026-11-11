<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuickQuiz extends Model
{
    protected $fillable = ['code', 'status', 'current_version_id', 'created_by', 'archived_at'];
    protected function casts(): array { return ['archived_at' => 'datetime']; }
    public function versions(): HasMany { return $this->hasMany(QuickQuizVersion::class); }
    public function currentVersion(): BelongsTo { return $this->belongsTo(QuickQuizVersion::class, 'current_version_id'); }
    public function draftVersion(): ?QuickQuizVersion { return $this->versions()->where('status','draft')->orderByDesc('version_number')->first(); }
}
