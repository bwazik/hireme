# Phase 0 — Project Scaffolding
**Timeline**: Day 1–2 | **Goal**: Migrations run, Filament loads at `/admin`

> 📌 **Before reading this phase**, open [`CONSTANTS.md`](./CONSTANTS.md). All rate limits, default values, and credentials referenced here are defined there.

> This project already exists at `c:\laragon\www\hireme`. Do NOT create a new Laravel app.
> Stack: Laravel 13, Filament 5, PHP 8.2+, MySQL, Queue=database.

---

## Pre-Flight Check — Before Creating Any Resources

This app already has a Filament panel. Verify its ID before generating resources:
```bash
php artisan filament:list-panels
```
If the ID is not `admin`, all `make:filament-resource` commands need `--panel=your-panel-id`.

---

## Step 1 — Install Packages

```bash
composer require phpmailer/phpmailer
composer require symfony/dom-crawler symfony/css-selector
```

Guzzle is already bundled with Laravel — skip it.

---

## Step 2 — Queue Table

```bash
php artisan queue:table
php artisan migrate
```

In `.env`:
```
QUEUE_CONNECTION=database
```

---

## Step 3 — Create 10 Migrations

Run each `make:migration` command then fill in the schema shown.

### companies
```bash
php artisan make:migration create_companies_table
```
```php
Schema::create('companies', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->json('industry')->nullable();
    $table->enum('size', ['unknown','small','medium','large'])->default('unknown');
    $table->string('country', 100)->nullable();
    $table->string('city', 100)->nullable();
    $table->string('website', 255)->nullable();
    $table->string('website_domain', 150)->nullable()->unique();
    $table->text('description')->nullable();
    $table->string('linkedin_url', 255)->nullable();
    $table->string('source', 50); // 'google_maps','linkedin','indeed','wellfound','manual','facebook'
    $table->text('source_url')->nullable();
    $table->text('notes')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->index('country');
    // $table->index('industry'); // JSON column doesn't support simple indexing
    $table->index('source');
});
```

### contacts
```bash
php artisan make:migration create_contacts_table
```
```php
Schema::create('contacts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('name', 255)->nullable();
    $table->string('role', 100)->nullable(); // 'HR','CTO','Founder','Recruiter'
    $table->string('email', 255)->nullable()->unique();
    $table->string('phone_raw', 50)->nullable();
    $table->string('phone', 30)->nullable()->unique(); // E.164: +201098617164
    $table->boolean('is_whatsapp')->default(false);
    $table->string('source', 50);
    $table->text('source_url')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->index('company_id');
});
```

### campaigns
```bash
php artisan make:migration create_campaigns_table
```
```php
Schema::create('campaigns', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->json('channels');                          // ["email","whatsapp"]
    $table->json('target_countries')->nullable();      // ["EG","DE"] — null=all
    $table->json('target_industries')->nullable();     // ["software_house","saas"] — null=all
    $table->json('target_cities')->nullable();         // ["Cairo"] — null=all
    $table->integer('daily_email_limit')->default(50);
    $table->integer('daily_whatsapp_limit')->default(30);
    $table->boolean('follow_up_enabled')->default(false);
    $table->integer('follow_up_days')->default(7);
    $table->enum('status', ['draft','active','paused','completed'])->default('draft');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('paused_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
```

### outreach_messages
```bash
php artisan make:migration create_outreach_messages_table
```
```php
Schema::create('outreach_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('campaign_id')->constrained('campaigns');
    $table->foreignId('contact_id')->constrained('contacts');
    $table->foreignId('company_id')->constrained('companies');
    $table->string('email_subject', 255)->nullable();
    $table->longText('email_body')->nullable();
    $table->text('whatsapp_message')->nullable();
    $table->string('ai_model', 100)->default('gemini-flash-latest');
    $table->integer('ai_prompt_tokens')->nullable();
    $table->integer('ai_output_tokens')->nullable();
    $table->timestamp('generated_at')->nullable();
    $table->boolean('approved')->default(true);
    $table->timestamp('created_at')->nullable();
    $table->index(['contact_id', 'campaign_id']);
});
```

### outreach_logs
```bash
php artisan make:migration create_outreach_logs_table
```
```php
Schema::create('outreach_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('campaign_id')->constrained('campaigns');
    $table->foreignId('contact_id')->constrained('contacts');
    $table->foreignId('company_id')->constrained('companies');
    $table->unsignedBigInteger('message_id')->nullable();
    $table->enum('channel', ['email','whatsapp']);
    $table->enum('status', ['pending','sent','failed','replied'])->default('pending');
    $table->unsignedBigInteger('email_account_id')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->text('failed_reason')->nullable();
    $table->timestamp('replied_at')->nullable();
    $table->text('reply_content')->nullable();
    $table->boolean('follow_up_sent')->default(false);
    $table->timestamp('follow_up_sent_at')->nullable();
    $table->timestamps();
    $table->index('status');
    $table->index('channel');
    $table->index('sent_at');
    $table->index(['contact_id', 'channel']);
});
```

### email_accounts
```bash
php artisan make:migration create_email_accounts_table
```
```php
Schema::create('email_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('email')->unique();
    $table->text('password'); // Laravel encrypted cast
    $table->string('sender_name', 100)->default('Abdullah Mohamed');
    $table->string('smtp_host', 100)->default('smtp.gmail.com');
    $table->integer('smtp_port')->default(587);
    $table->string('smtp_encryption', 10)->default('tls');
    $table->integer('daily_limit')->default(80);
    $table->integer('sent_today')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_used_at')->nullable();
    $table->timestamp('last_reset_at')->nullable();
    $table->timestamps();
});
```

### whatsapp_instances
```bash
php artisan make:migration create_whatsapp_instances_table
```
```php
Schema::create('whatsapp_instances', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);       // e.g. 'BanhaFade'
    $table->string('api_url', 255);    // e.g. 'https://whatsapp.shattor.com'
    $table->text('api_key'); // encrypted cast
    $table->string('phone_number', 30)->nullable();
    $table->integer('daily_limit')->default(50);
    $table->integer('sent_today')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_used_at')->nullable();
    $table->timestamps();
});
```

### scraping_jobs
```bash
php artisan make:migration create_scraping_jobs_table
```
```php
Schema::create('scraping_jobs', function (Blueprint $table) {
    $table->id();
    $table->enum('source', ['serper_maps','serper_search','indeed','wellfound','linkedin','manual']);
    $table->string('query', 255);
    $table->string('location', 100)->nullable();
    $table->string('country_code', 5)->nullable();
    $table->enum('status', ['pending','running','completed','failed'])->default('pending');
    $table->integer('results_found')->default(0);
    $table->integer('new_leads')->default(0);
    $table->integer('duplicates')->default(0);
    $table->text('error_message')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('created_at')->nullable();
});
```

### scraping_results
```bash
php artisan make:migration create_scraping_results_table
```
```php
Schema::create('scraping_results', function (Blueprint $table) {
    $table->id();
    $table->foreignId('scraping_job_id')->constrained('scraping_jobs')->cascadeOnDelete();
    $table->json('raw_data');
    $table->string('company_name', 255)->nullable();
    $table->string('website', 255)->nullable();
    $table->string('email', 255)->nullable();
    $table->string('phone', 50)->nullable();
    $table->string('location', 255)->nullable();
    $table->text('source_url')->nullable();
    $table->boolean('processed')->default(false);
    $table->timestamp('created_at')->nullable();
    $table->index('processed');
});
```

### system_settings
```bash
php artisan make:migration create_system_settings_table
```
```php
Schema::create('system_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key', 100)->unique();
    $table->text('value')->nullable();
    $table->string('description', 255)->nullable();
    $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
});
```

---

## Step 4 — Run All Migrations

```bash
php artisan migrate
```

---

## Step 5 — SystemSetting Model

```bash
php artisan make:model SystemSetting
```

`app/Models/SystemSetting.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'value', 'description'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("system_setting_{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, mixed $value, ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description]
        );
        Cache::forget("system_setting_{$key}");
    }

    // Auto-encrypt/decrypt sensitive keys
    public function getValueAttribute($value)
    {
        if (is_string($this->key) && str_ends_with($this->key, '_key') && !empty($value)) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return $value; 
            }
        }
        return $value;
    }

    public function setValueAttribute($value)
    {
        if (is_string($this->key) && str_ends_with($this->key, '_key') && !empty($value)) {
            $this->attributes['value'] = encrypt($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }
}
```

---

## Step 6 — Seed Default Settings

```bash
php artisan make:seeder SystemSettingsSeeder
```

`database/seeders/SystemSettingsSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'gemini_api_key',       'value' => config('services.gemini.key', ''), 'description' => 'Google Gemini API key (X-goog-api-key header)'],
            ['key' => 'serper_api_key',        'value' => config('services.serper.key', ''), 'description' => 'Serper.dev API key (X-API-KEY header)'],
            ['key' => 'whatsapp_delay_min',    'value' => '60',   'description' => 'Min seconds between WA messages'],
            ['key' => 'whatsapp_delay_max',    'value' => '120',  'description' => 'Max seconds between WA messages'],
            ['key' => 'email_cv_path',         'value' => 'cv/Abdullah_Mohamed_CV.pdf', 'description' => 'CV path inside storage/app/'],
            ['key' => 'discovery_enabled',     'value' => 'true', 'description' => 'Toggle discovery engine'],
            ['key' => 'sending_enabled',       'value' => 'true', 'description' => 'Toggle message sending'],
            ['key' => 'ai_generation_enabled', 'value' => 'true', 'description' => 'Toggle AI generation'],
            ['key' => 'daily_start_hour',      'value' => '9',    'description' => 'No sending before this hour'],
            ['key' => 'daily_end_hour',        'value' => '22',   'description' => 'No sending after this hour'],
            ['key' => 'dry_run',              'value' => 'true', 'description' => 'When true: log messages but do NOT call SMTP or WhatsApp API. Set to false when ready to go live.'],
            ['key' => 'serper_queries_used',   'value' => '0',    'description' => 'Serper.dev usage counter (2500 total free)'],
        ];

        foreach ($defaults as $row) {
            SystemSetting::set($row['key'], $row['value'], $row['description']);
        }
    }
}
```

```bash
php artisan db:seed --class=SystemSettingsSeeder
```

---

## Step 7 — Config & Env

Add to `config/services.php`:
```php
'gemini' => [
    'key' => env('GEMINI_API_KEY'),   // auth header X-goog-api-key in GeminiClient
],
'serper' => [
    'key' => env('SERPER_API_KEY'),   // header X-API-KEY in SerperSources
],
```

> **The following are already set in `.env`** — just verify they exist:
```
GEMINI_API_KEY=YOUR_GEMINI_API_KEY
SERPER_API_KEY=YOUR_SERPER_API_KEY
GMAIL_APP_PASSWORD=YOUR_GMAIL_APP_PASSWORD
QUEUE_CONNECTION=database
```

Seed the API keys into `system_settings` so the services can read them at runtime (they will be automatically encrypted by the model):
```bash
php artisan tinker --execute '
\App\Models\SystemSetting::set("gemini_api_key", config("services.gemini.key"));
\App\Models\SystemSetting::set("serper_api_key", config("services.serper.key"));
echo "Done.";
'
```

---

## Step 8 — CV Directory

```bash
mkdir storage\app\cv
```

Place `Abdullah_Mohamed_CV.pdf` here. Create an empty placeholder if the file doesn't exist yet.

---

## Deliverable

- [ ] `php artisan migrate:status` — all 10 custom tables show "Ran"
- [ ] `http://localhost:8000/admin` loads Filament dashboard
- [ ] `system_settings` table has 11 rows
- [ ] `storage/app/cv/` directory exists

**Next**: Phase 1 — Lead Management (Models + Filament Resources + Deduplication)
