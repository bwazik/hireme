<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'scraping_job_id', 'raw_data', 'company_name', 'website',
    'email', 'phone', 'location', 'source_url', 'processed', 'created_at',
])]
class ScrapingResult extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'raw_data'   => 'array',
            'processed'  => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function scrapingJob(): BelongsTo
    {
        return $this->belongsTo(ScrapingJob::class);
    }
}
