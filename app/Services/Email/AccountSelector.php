<?php

namespace App\Services\Email;

use App\Models\EmailAccount;
use App\Models\SystemSetting;

class AccountSelector
{
    /**
     * Pick the least-recently-used active email account that hasn't hit its daily limit.
     * Returns null if all accounts are exhausted.
     */
    public function select(): ?EmailAccount
    {
        // Time window check removed per user request

        return EmailAccount::query()
            ->where('is_active', true)
            ->whereRaw('sent_today < daily_limit')
            ->orderBy('last_used_at', 'asc')
            ->first();
    }
}
