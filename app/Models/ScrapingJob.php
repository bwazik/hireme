<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'source', 'query', 'location', 'country_code', 'status',
    'results_found', 'new_leads', 'duplicates',
    'error_message', 'started_at', 'completed_at', 'created_at',
])]
class ScrapingJob extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(ScrapingResult::class);
    }
}
