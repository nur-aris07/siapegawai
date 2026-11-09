<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TryoutPackage extends Model
{
    protected $fillable = ['code','name','description','duration_minutes','max_attempts','status','published_at','archived_at','created_by'];
    protected function casts(): array { return ['duration_minutes'=>'integer','max_attempts'=>'integer','published_at'=>'datetime','archived_at'=>'datetime']; }
    public function items(): HasMany { return $this->hasMany(TryoutPackageItem::class)->orderBy('sort_order'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
