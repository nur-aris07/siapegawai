<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Question extends Model
{
    protected $fillable = ['topic_id', 'code', 'status', 'current_version_id', 'created_by'];
    public function topic(): BelongsTo { return $this->belongsTo(Topic::class); }
    public function versions(): HasMany { return $this->hasMany(QuestionVersion::class); }
    public function latestVersion(): HasOne { return $this->hasOne(QuestionVersion::class)->latestOfMany('version_number'); }
    public function currentVersion(): BelongsTo { return $this->belongsTo(QuestionVersion::class, 'current_version_id'); }
}
