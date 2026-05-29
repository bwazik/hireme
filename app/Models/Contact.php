<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'company_id', 'name', 'role', 'email', 'phone_raw',
    'phone', 'is_whatsapp', 'source', 'source_url', 'is_active',
    'is_guessed',
])]
class Contact extends Model
{
    protected function casts(): array
    {
        return [
            'is_whatsapp' => 'boolean',
            'is_active'   => 'boolean',
            'is_guessed'  => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
