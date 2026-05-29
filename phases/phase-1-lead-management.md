# Phase 1 — Lead Management
**Timeline**: Day 3–5 | **Goal**: Manually add companies/contacts via Filament, deduplication works, all models have relationships.

> 📌 **Reference [`CONSTANTS.md`](./CONSTANTS.md)** for: industry classification keywords (§14), source values (§15), rate limits (§6), default settings (§7).

> **Prerequisite**: Phase 0 complete — all 10 migrations ran, Filament loads at `/admin`.

---

## Context

You are building the data layer for an autonomous outreach system. This phase creates:
1. All 10 Eloquent models with relationships
2. Filament resources for Companies, Contacts, Email Accounts, WhatsApp Instances
3. `LeadProcessor` service with deduplication and phone normalization

---

## Step 1 — Create All Models

```bash
php artisan make:model Company
php artisan make:model Contact
php artisan make:model Campaign
php artisan make:model OutreachMessage
php artisan make:model OutreachLog
php artisan make:model EmailAccount
php artisan make:model WhatsappInstance
php artisan make:model ScrapingJob
php artisan make:model ScrapingResult
```

`SystemSetting` was already created in Phase 0.

---

### `app/Models/Company.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'name', 'industry', 'size', 'country', 'city',
    'website', 'website_domain', 'description',
    'linkedin_url', 'source', 'source_url', 'notes', 'is_active',
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
```

### `app/Models/Contact.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'company_id', 'name', 'role', 'email', 'phone_raw',
    'phone', 'is_whatsapp', 'source', 'source_url', 'is_active',
])]
class Contact extends Model
{
    protected function casts(): array
    {
        return [
            'is_whatsapp' => 'boolean',
            'is_active'   => 'boolean',
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
```

### `app/Models/Campaign.php`

```php
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
```

### `app/Models/OutreachMessage.php`

```php
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
```

### `app/Models/OutreachLog.php`

```php
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
```

### `app/Models/EmailAccount.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'email', 'password', 'sender_name', 'smtp_host',
    'smtp_port', 'smtp_encryption', 'daily_limit',
    'sent_today', 'is_active', 'last_used_at', 'last_reset_at',
])]
class EmailAccount extends Model
{
    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'last_used_at' => 'datetime',
            'last_reset_at'=> 'datetime',
            'password'     => 'encrypted',
        ];
    }
}
```

### `app/Models/WhatsappInstance.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'name', 'api_url', 'api_key', 'phone_number',
    'daily_limit', 'sent_today', 'is_active', 'last_used_at',
])]
class WhatsappInstance extends Model
{
    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'last_used_at'=> 'datetime',
            'api_key'     => 'encrypted',
        ];
    }
}
```

### `app/Models/ScrapingJob.php`

```php
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
```

### `app/Models/ScrapingResult.php`

```php
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
```

---

## Step 2 — LeadProcessor Service

Create `app/Services/Discovery/LeadProcessor.php`:

```php
<?php

namespace App\Services\Discovery;

use App\Models\Company;
use App\Models\Contact;

class LeadProcessor
{
    /**
     * Normalise a raw domain string like 'https://www.example.com/page' → 'example.com'
     */
    public function normalizeDomain(string $url): string
    {
        $host = parse_url(trim($url), PHP_URL_HOST) ?? $url;

        return strtolower(preg_replace('/^www\./', '', $host));
    }

    /**
     * Normalise a phone number to E.164 format.
     * countryCode: 'EG' → prefix '20', 'DE' → '49', 'US' → '1', etc.
     */
    public function normalizePhone(string $rawPhone, string $countryCode = 'EG'): ?string
    {
        // Strip all non-digit characters except leading +
        $digits = preg_replace('/[^\d+]/', '', $rawPhone);

        // Already in E.164
        if (str_starts_with($digits, '+')) {
            return strlen($digits) >= 10 ? $digits : null;
        }

        // Remove leading 00
        if (str_starts_with($digits, '00')) {
            $digits = '+' . substr($digits, 2);
            return strlen($digits) >= 10 ? $digits : null;
        }

        $prefixMap = [
            'EG' => '20', 'DE' => '49', 'US' => '1',
            'CH' => '41', 'GB' => '44', 'AE' => '971',
        ];

        $prefix = $prefixMap[strtoupper($countryCode)] ?? null;

        if (! $prefix) {
            return null;
        }

        // Egyptian numbers: strip leading 0 before adding country code
        if ($countryCode === 'EG' && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        $e164 = '+' . $prefix . $digits;

        return strlen($e164) >= 10 ? $e164 : null;
    }

    /**
     * Auto-classify industries from a text description.
     * @return string[]
     */
    public function classifyIndustry(string $text): array
    {
        $text = strtolower($text);
        $found = [];

        $rules = [
            'ai_company'     => ['ai', 'artificial intelligence', 'machine learning', ' ml ', 'deep learning', 'neural', 'llm', 'gpt'],
            'saas'           => ['saas', 'platform', 'subscription', 'cloud-based', 'software as a service'],
            'mobile_dev'     => ['mobile', 'ios', 'android', ' app ', 'flutter', 'react native', 'swift', 'kotlin'],
            'fintech'        => ['fintech', 'banking', 'payment', 'wallet', 'crypto', 'blockchain', 'financial technology'],
            'e_commerce'     => ['shopify', 'woocommerce', 'magento', 'e-commerce', 'online store', 'retail tech'],
            'digital_agency' => ['digital marketing', 'seo', 'social media', 'branding', 'advertising', 'marketing agency'],
            'web_design'     => ['web design', 'ui/ux', 'creative', 'design agency', 'frontend', 'user experience', 'user interface'],
            'software_house' => ['software', 'development', 'tech', ' it ', 'backend', 'fullstack', 'consulting', 'system integrator', 'programming'],
        ];

        foreach ($rules as $label => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    $found[] = $label;
                    break;
                }
            }
        }

        return ! empty($found) ? array_unique($found) : ['other'];
    }

    /**
     * Process a single company+contact payload and save to DB (with dedup).
     *
     * @param  array{name:string, website:?string, country:?string, city:?string, description:?string, source:string, source_url:?string}  $companyData
     * @param  array{email:?string, phone:?string, name:?string, role:?string, source:string}  $contactData
     * @param  string  $countryCode  Used for phone normalisation.
     * @return array{company:Company|null, contact:Contact|null, skipped:bool, reason:string}
     */
    public function process(array $companyData, array $contactData, string $countryCode = 'EG'): array
    {
        // ── Company dedup ──────────────────────────────────────────────────
        $domain = null;

        if (! empty($companyData['website'])) {
            $domain = $this->normalizeDomain($companyData['website']);
        }

        if ($domain && Company::where('website_domain', $domain)->exists()) {
            return ['company' => null, 'contact' => null, 'skipped' => true, 'reason' => 'duplicate_domain'];
        }

        // Classify industry from description
        $industry = $this->classifyIndustry($companyData['description'] ?? $companyData['name'] ?? '');

        $company = Company::create([
            'name'           => $companyData['name'],
            'industry'       => $industry,
            'country'        => $companyData['country'] ?? null,
            'city'           => $companyData['city'] ?? null,
            'website'        => $companyData['website'] ?? null,
            'website_domain' => $domain,
            'description'    => $companyData['description'] ?? null,
            'source'         => $companyData['source'],
            'source_url'     => $companyData['source_url'] ?? null,
        ]);

        // ── Contact dedup ──────────────────────────────────────────────────
        $email      = ! empty($contactData['email']) ? strtolower(trim($contactData['email'])) : null;
        $phoneRaw   = $contactData['phone'] ?? null;
        $phoneE164  = $phoneRaw ? $this->normalizePhone($phoneRaw, $countryCode) : null;

        if ($email && Contact::where('email', $email)->exists()) {
            return ['company' => $company, 'contact' => null, 'skipped' => true, 'reason' => 'duplicate_email'];
        }

        if ($phoneE164 && Contact::where('phone', $phoneE164)->exists()) {
            return ['company' => $company, 'contact' => null, 'skipped' => true, 'reason' => 'duplicate_phone'];
        }

        $contact = Contact::create([
            'company_id' => $company->id,
            'name'       => $contactData['name'] ?? null,
            'role'       => $contactData['role'] ?? null,
            'email'      => $email,
            'phone_raw'  => $phoneRaw,
            'phone'      => $phoneE164,
            'is_whatsapp'=> ! empty($phoneE164),
            'source'     => $contactData['source'],
            'source_url' => $contactData['source_url'] ?? null,
        ]);

        return ['company' => $company, 'contact' => $contact, 'skipped' => false, 'reason' => 'created'];
    }
}
```

---

## Step 3 — Filament Resources

### CompanyResource

```bash
php artisan make:filament-resource Company --generate
```

> **Add to every resource class (`app/Filament/Resources/CompanyResource.php`)** so all outreach resources are grouped in the sidebar:
> ```php
> protected static string|\UnitEnum|null $navigationGroup = 'Outreach';
protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;
> ```

Edit `app/Filament/Resources/Companies/Schemas/CompanyForm.php` — set the form:

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

// inside components():
TextInput::make('name')->required(),
Select::make('industry')
    ->multiple()
    ->options([
    'software_house' => 'Software House', 'saas' => 'SaaS', 'mobile_dev' => 'Mobile Dev',
    'fintech' => 'FinTech', 'e_commerce' => 'E-Commerce',
    'digital_agency' => 'Digital Agency', 'ai_company' => 'AI Company', 'web_design' => 'Web Design', 'other' => 'Other',
]),
Select::make('size')->options([
    'unknown' => 'Unknown', 'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large',
])->default('unknown'),
TextInput::make('country'),
TextInput::make('city'),
TextInput::make('website')->url(),
TextInput::make('website_domain'),
Textarea::make('description'),
TextInput::make('linkedin_url')->url(),
Select::make('source')->options([
    'google_maps' => 'Google Maps', 'serper_search' => 'Serper Search', 'linkedin' => 'LinkedIn',
    'indeed' => 'Indeed', 'wellfound' => 'Wellfound', 'manual' => 'Manual', 'facebook' => 'Facebook',
])->required(),
Textarea::make('notes'),
Toggle::make('is_active')->default(true),
```

Edit `app/Filament/Resources/Companies/Tables/CompaniesTable.php` — set the table:

```php
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

// inside columns():
TextColumn::make('name')->searchable()->sortable(),
TextColumn::make('industry')->badge(),
TextColumn::make('country')->sortable(),
TextColumn::make('city'),
TextColumn::make('contacts_count')->counts('contacts')->label('Contacts'),
TextColumn::make('source')->badge(),
IconColumn::make('is_active')->boolean(),
TextColumn::make('created_at')->dateTime()->sortable(),

// inside filters():
SelectFilter::make('industry')
    ->multiple()
    ->options([
        'software_house' => 'Software House', 'saas' => 'SaaS', 'mobile_dev' => 'Mobile Dev',
        'fintech' => 'FinTech', 'e_commerce' => 'E-Commerce',
        'digital_agency' => 'Digital Agency', 'ai_company' => 'AI Company', 'web_design' => 'Web Design', 'other' => 'Other',
    ])
    ->query(function ($query, array $data) {
        if (empty($data['values'])) return $query;
        return $query->where(function ($q) use ($data) {
            foreach ($data['values'] as $value) {
                $q->orWhereJsonContains('industry', $value);
            }
        });
    }),
SelectFilter::make('source')->options([
    'google_maps' => 'Google Maps', 'serper_search' => 'Serper Search', 'linkedin' => 'LinkedIn',
    'indeed' => 'Indeed', 'wellfound' => 'Wellfound', 'manual' => 'Manual', 'facebook' => 'Facebook',
]),
SelectFilter::make('country'),
TernaryFilter::make('is_active'),
```

### ContactResource

```bash
php artisan make:filament-resource Contact --generate
```

> **Resource Class Setup (`app/Filament/Resources/ContactResource.php`):**
> ```php
> protected static string|\UnitEnum|null $navigationGroup = 'Outreach';
> protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
> ```

Edit `app/Filament/Resources/Contacts/Schemas/ContactForm.php` — set the form:

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

// inside components():
Select::make('company_id')
    ->relationship('company', 'name')
    ->searchable()
    ->preload()
    ->required(),
TextInput::make('name'),
TextInput::make('role'),
TextInput::make('email')->email(),
TextInput::make('phone_raw'),
TextInput::make('phone')->helperText('E.164 format: +201098617164'),
Toggle::make('is_whatsapp'),
Select::make('source')->options([
    'google_maps'   => 'Google Maps',
    'serper_search' => 'Serper Search',
    'linkedin'      => 'LinkedIn',
    'indeed'        => 'Indeed',
    'wellfound'     => 'Wellfound',
    'manual'        => 'Manual',
    'facebook'      => 'Facebook',
])->required(),
Toggle::make('is_active')->default(true),
```

Edit `app/Filament/Resources/Contacts/Tables/ContactsTable.php` — set the table:

```php
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

// inside columns():
TextColumn::make('name')->searchable(),
TextColumn::make('email')->searchable(),
TextColumn::make('phone'),
TextColumn::make('company.name')->label('Company')->searchable(),
IconColumn::make('is_whatsapp')->boolean()->label('WA?'),
TextColumn::make('outreachLogs_count')->counts('outreachLogs')->label('Sent'),
IconColumn::make('is_active')->boolean(),
```

### EmailAccountResource

```bash
php artisan make:filament-resource EmailAccount --generate
```

> **Resource Class Setup (`app/Filament/Resources/EmailAccountResource.php`):**
> ```php
> protected static string|\UnitEnum|null $navigationGroup = 'Outreach';
> protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;
> ```

Edit `app/Filament/Resources/EmailAccounts/Schemas/EmailAccountForm.php`:

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

// inside components():
TextInput::make('email')->email()->required(),
TextInput::make('password')->password()->required()
    ->helperText('Will be encrypted with Laravel encrypt() automatically because of model cast.'),
TextInput::make('sender_name')->default('Abdullah Mohamed'),
TextInput::make('smtp_host')->default('smtp.gmail.com'),
TextInput::make('smtp_port')->numeric()->default(587),
Select::make('smtp_encryption')->options(['tls'=>'TLS','ssl'=>'SSL'])->default('tls'),
TextInput::make('daily_limit')->numeric()->default(80),
Toggle::make('is_active')->default(true),
```

Edit `app/Filament/Resources/EmailAccounts/Tables/EmailAccountsTable.php`:

```php
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

// inside columns():
TextColumn::make('email')->searchable(),
TextColumn::make('sender_name'),
TextColumn::make('sent_today')->label('Sent Today'),
TextColumn::make('daily_limit')->label('Daily Limit'),
IconColumn::make('is_active')->boolean(),
TextColumn::make('last_used_at')->dateTime(),
```

### WhatsappInstanceResource

```bash
php artisan make:filament-resource WhatsappInstance --generate
```

> **Resource Class Setup (`app/Filament/Resources/WhatsappInstanceResource.php`):**
> ```php
> protected static string|\UnitEnum|null $navigationGroup = 'Outreach';
> protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;
> ```

Edit `app/Filament/Resources/WhatsappInstances/Schemas/WhatsappInstanceForm.php`:

```php
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

// inside components():
TextInput::make('name')->required()->helperText("e.g. 'BanhaFade'"),
TextInput::make('api_url')->url()->required()->helperText("e.g. 'https://whatsapp.shattor.com'"),
TextInput::make('api_key')->password()->required()->helperText('Will be encrypted automatically because of model cast.'),
TextInput::make('phone_number'),
TextInput::make('daily_limit')->numeric()->default(50),
Toggle::make('is_active')->default(true),
```

Edit `app/Filament/Resources/WhatsappInstances/Tables/WhatsappInstancesTable.php`:

```php
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

// inside columns():
TextColumn::make('name')->searchable(),
TextColumn::make('api_url'),
TextColumn::make('phone_number')->searchable(),
TextColumn::make('sent_today')->label('Sent Today'),
TextColumn::make('daily_limit')->label('Daily Limit'),
IconColumn::make('is_active')->boolean(),
```

---

## Step 4 — Test Deduplication

In Tinker, manually test the `LeadProcessor`:

```bash
php artisan tinker --execute '
$processor = new \App\Services\Discovery\LeadProcessor();
$result = $processor->process(
    ["name"=>"Test Co","website"=>"https://www.testco.com","source"=>"manual"],
    ["email"=>"hr@testco.com","source"=>"manual"]
);
echo $result["reason"];
'
```

Run it twice — the second time should print `duplicate_domain`.

---

## Deliverable

- [ ] All 9 models created with correct relationships
- [ ] `CompanyResource`, `ContactResource`, `EmailAccountResource`, `WhatsappInstanceResource` visible in Filament sidebar
- [ ] Can create a company through Filament UI
- [ ] Can create a contact linked to that company
- [ ] Running `LeadProcessor::process()` twice with the same domain returns `skipped=true` on the second run
- [ ] Email account passwords are encrypted in DB (verify with `SELECT password FROM email_accounts`)

**Next**: Phase 2 — AI Message Generator (Gemini Flash integration)
