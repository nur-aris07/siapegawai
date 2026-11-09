<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = ['code', 'name', 'status', 'sort_order'];
    public function topics(): HasMany { return $this->hasMany(Topic::class); }
}
