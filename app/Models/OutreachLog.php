<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'campaign_id', 'contact_id', 'company_id', 'message_id',
    'channel', 'status', 'email_account_id', 'sent_at',
    'failed_reason', 'replied_at', 'reply_content',
    'follow_up_sent', 'follow_up_sent_at',
])]
class OutreachLog extends Model
{
    protected function casts(): array
    {
        return [
            'sent_at'          => 'datetime',
            'replied_at'       => 'datetime',
            'follow_up_sent'   => 'boolean',
            'follow_up_sent_at'=> 'datetime',
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

    public function outreachMessage(): BelongsTo
    {
        return $this->belongsTo(OutreachMessage::class, 'message_id');
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }
}
