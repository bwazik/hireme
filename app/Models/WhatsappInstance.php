<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'name', 'api_url', 'api_key', 'phone_number',
    'daily_limit', 'sent_today', 'is_active', 'last_used_at',
])]
class WhatsappInstance extends Model
{
    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'last_used_at'=> 'datetime',
            'api_key'     => 'encrypted',
        ];
    }
}
