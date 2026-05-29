<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Collection;

class CampaignContactSelector
{
    /**
     * Return all contacts eligible for this campaign (not yet contacted by it).
     */
    public function selectContacts(Campaign $campaign): Collection
    {
        return Contact::query()
            ->with('company')
            ->whereHas('company', function ($q) use ($campaign) {
                $q->where('is_active', true);

                // If named Outreach - YYYY-MM-DD, restrict to that day's companies
                if (preg_match('/^Outreach - (\d{4}-\d{2}-\d{2})$/', $campaign->name, $matches)) {
                    $q->whereDate('created_at', $matches[1]);
                }

                if (! empty($campaign->target_countries)) {
                    $q->whereIn('country', $campaign->target_countries);
                }

                if (! empty($campaign->target_industries)) {
                    // industry is a JSON column — match if any value in the array overlaps
                    $q->where(function ($inner) use ($campaign) {
                        foreach ($campaign->target_industries as $industry) {
                            $inner->orWhereJsonContains('industry', $industry);
                        }
                    });
                }

                if (! empty($campaign->target_cities)) {
                    $q->whereIn('city', $campaign->target_cities);
                }
            })
            ->where('is_active', true)
            // Not already contacted by this campaign
            ->whereDoesntHave('outreachLogs', fn ($q) => $q->where('campaign_id', $campaign->id))
            // Must have at least one channel available
            ->where(function ($q) use ($campaign) {
                $channels = $campaign->channels ?? [];

                $q->where(function ($inner) use ($channels) {
                    if (in_array('email', $channels)) {
                        $inner->whereNotNull('email');
                    }
                })
                ->orWhere(function ($inner) use ($channels) {
                    if (in_array('whatsapp', $channels)) {
                        $inner->whereNotNull('phone');
                    }
                });
            })
            ->get();
    }
}
