<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'name', 'industry', 'size', 'country', 'city',
    'website', 'website_domain', 'description',
    'linkedin_url', 'source', 'source_url', 'notes', 'is_active',
    'contact_status',
])]
class Company extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'industry'  => 'array',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
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
