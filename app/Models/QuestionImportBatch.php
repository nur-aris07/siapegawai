<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionImportBatch extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','actor_id','private_path','sha256','byte_count','row_count',
        'valid_count','invalid_count','status','errors','result','failure_reason','expires_at','committed_at'];
    protected function casts(): array
    {
        return ['errors'=>'array', 'result'=>'array', 'expires_at'=>'datetime', 'committed_at'=>'datetime'];
    }
}
