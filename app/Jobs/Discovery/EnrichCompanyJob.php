<?php

namespace App\Jobs\Discovery;

use App\Models\Company;
use App\Models\Contact;
use App\Services\Discovery\Extractors\ContactExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnrichCompanyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function __construct(
        public Company $company
    ) {}

    public function handle(ContactExtractor $contactExtractor): void
    {
        if (! $this->company->website) {
            $this->company->update(['contact_status' => 'no_website']);
            return;
        }

        // Try extracting again (maybe a different strategy like pattern guessing)
        $contactInfo = $contactExtractor->extract($this->company->website);

        $email = $contactInfo['emails'][0] ?? null;
        $phone = $contactInfo['phones'][0] ?? null;

        if ($email || $phone) {
            // Found something!
            Contact::create([
                'company_id'  => $this->company->id,
                'email'       => $email ? strtolower(trim($email)) : null,
                'phone'       => $phone,
                'phone_raw'   => $phone,
                'is_whatsapp' => ! empty($phone),
                'source'      => 'enrichment_job',
                'source_url'  => $this->company->website,
                'is_guessed'  => empty($contactInfo['emails']) && $email ? true : false,
            ]);

            $this->company->update(['contact_status' => 'has_contacts']);
        } else {
            // Still nothing
            $this->company->update(['contact_status' => 'manual_required']);
        }
    }
}
