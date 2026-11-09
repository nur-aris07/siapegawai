<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TryoutAttemptAnswer extends Model
{
    protected $fillable = ['tryout_attempt_id','tryout_package_item_id','answer_label','flagged'];
    protected function casts(): array { return ['flagged'=>'boolean']; }
    public function attempt(): BelongsTo { return $this->belongsTo(TryoutAttempt::class,'tryout_attempt_id'); }
    public function item(): BelongsTo { return $this->belongsTo(TryoutPackageItem::class,'tryout_package_item_id'); }
}
