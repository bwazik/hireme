<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'email', 'password', 'sender_name', 'smtp_host',
    'smtp_port', 'smtp_encryption', 'daily_limit',
    'sent_today', 'is_active', 'last_used_at', 'last_reset_at',
])]
class EmailAccount extends Model
{
    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'last_used_at' => 'datetime',
            'last_reset_at'=> 'datetime',
            'password'     => 'encrypted',
        ];
    }
}
