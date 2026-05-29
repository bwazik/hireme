<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'name', 'description', 'channels', 'target_countries',
    'target_industries', 'target_cities', 'daily_email_limit',
    'daily_whatsapp_limit', 'follow_up_enabled', 'follow_up_days',
    'status', 'started_at', 'paused_at', 'completed_at',
])]
class Campaign extends Model
{
    protected function casts(): array
    {
        return [
            'channels'          => 'array',
            'target_countries'  => 'array',
            'target_industries' => 'array',
            'target_cities'     => 'array',
            'follow_up_enabled' => 'boolean',
            'started_at'        => 'datetime',
            'paused_at'         => 'datetime',
            'completed_at'      => 'datetime',
        ];
    }

    public function outreachLogs(): HasMany
    {
        return $this->hasMany(OutreachLog::class);
    }

    public function outreachMessages(): HasMany
    {
        return $this->hasMany(OutreachMessage::class);
    }
}
