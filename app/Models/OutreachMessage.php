<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'campaign_id', 'contact_id', 'company_id', 'email_subject',
    'email_body', 'whatsapp_message', 'ai_model',
    'ai_prompt_tokens', 'ai_output_tokens', 'generated_at', 'approved',
])]
class OutreachMessage extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'approved'     => 'boolean',
            'generated_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
