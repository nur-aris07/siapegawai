<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountEmailOutbox extends Model
{
    protected $table = 'account_email_outbox';
    protected $guarded = ['id'];
    protected $casts = ['sent_at'=>'datetime', 'last_attempt_at'=>'datetime', 'attempts'=>'integer'];
}
