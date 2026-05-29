# Phase 2 — AI Message Generator
**Timeline**: Day 6–8 | **Goal**: Gemini Flash generates personalized email + WhatsApp messages per company. Preview in Filament UI.

> 📌 **Reference [`CONSTANTS.md`](./CONSTANTS.md)** for: Abdullah's AI profile (§3), portfolio links (§2), fallback messages (§4), Gemini API details (§5.1), rate limits (§6), queue order (§12).

> **Prerequisite**: Phase 1 complete — all models exist, CompanyResource works.

---

## Context

This phase integrates Google Gemini Flash (latest) to generate unique, personalized outreach messages for each company. The AI receives Abdullah's profile + company context and returns a JSON object with `email_subject`, `email_body`, and `whatsapp_message`.

**Advanced Features Included:**
- **Multilingual Support:** Auto-detects target language (Arabic for Egypt, English otherwise).
- **Stack Adaptation:** Detects if a company uses non-PHP stacks (e.g., React, Node, C#) and dynamically shifts the prompt to emphasize adaptability and CS fundamentals.
- **Context-Aware WhatsApp Teaser:** Changes the WhatsApp message to a "teaser" pointing to the email inbox if an email is also sent, or a full pitch if WhatsApp is the only channel.

**Gemini Free Tier**: 1,500 requests/day, 15 RPM. We use ~50/day — extremely safe.

**Auth**: Header `X-goog-api-key` (NOT a query param — see `CONSTANTS.md` Section 5.1)

**Gemini API Endpoint**:
```
POST https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent
Header: X-goog-api-key: {GEMINI_API_KEY}
```

---

## Step 1 — Create the Service Directory Structure

```
app/Services/AI/
├── GeminiClient.php
├── MessageGenerator.php
└── Prompts/
    └── MessagePromptBuilder.php
```

---

## Step 2 — GeminiClient.php

Create `app/Services/AI/GeminiClient.php`:

```php
<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient
{
    private string $apiKey;
    private string $model    = 'gemini-flash-latest';
    private string $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        // Reads from system_settings first, falls back to .env GEMINI_API_KEY
        $this->apiKey = \App\Models\SystemSetting::get('gemini_api_key')
            ?: config('services.gemini.key', '');
    }

    /**
     * Generate content using Gemini Flash.
     *
     * @throws \RuntimeException if API key is missing or request fails
     */
    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.8): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API key is not configured. Set GEMINI_API_KEY in .env or gemini_api_key in system_settings.');
        }

        $url = $this->endpoint . $this->model . ':generateContent';

        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type'    => 'application/json',
                'X-goog-api-key'  => $this->apiKey,   // Auth via header, NOT query param
            ])
            ->post($url, [
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [['text' => $userPrompt]],
                    ],
                ],
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'generationConfig' => [
                    'temperature'     => $temperature,
                    'maxOutputTokens' => 1500,
                ],
            ]);

        if ($response->failed()) {
            Log::error('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Gemini API returned HTTP ' . $response->status());
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }
}
```

---

## Step 3 — MessagePromptBuilder.php

Create `app/Services/AI/Prompts/MessagePromptBuilder.php`:

```php
<?php

namespace App\Services\AI\Prompts;

use App\Models\Company;
use App\Models\Contact;

class MessagePromptBuilder
{
    public function buildSystemPrompt(): string
    {
        // ⚠️  COPY THIS PROMPT FROM CONSTANTS.md → Section 3 ("AI System Prompt — Abdullah's Profile Block")
        // The profile block is maintained there as the single source of truth.
        // Paste the full text between the heredoc markers below.
        return <<<'PROMPT'
[PASTE FULL CONTENT FROM CONSTANTS.md §3 HERE]
PROMPT;
    }

    public function buildUserPrompt(Company $company, Contact $contact): string
    {
        $lines = ["Company name: {$company->name}"];

        if ($company->industry) {
            $lines[] = "Industry: {$company->industry}";
        }
        if ($company->city) {
            $lines[] = "City: {$company->city}";
        }
        if ($company->country) {
            $lines[] = "Country: {$company->country}";
        }
        if ($company->website) {
            $lines[] = "Website: {$company->website}";
        }
        if ($company->description) {
            $lines[] = "About them: {$company->description}";
        }
        if ($contact->role) {
            $lines[] = "Contact role: {$contact->role}";
        }
        if ($contact->name) {
            $lines[] = "Contact name: {$contact->name}";
        }

        $companyContext = implode("\n", $lines);

        return <<<PROMPT
Generate a personalized outreach message for Abdullah Mohamed to send to the following company.
Abdullah is looking for work — he wants to join their team or collaborate as a freelancer/contractor.

== TARGET COMPANY ==
{$companyContext}

== INSTRUCTIONS ==
- Reference something specific about this company if possible (their industry, location, what they build)
- The email should feel like Abdullah personally wrote it after actually looking at their company
- The WhatsApp message should be shorter, more informal, and direct
- If the company is in Egypt, use a slightly more personal tone
- If contact has a name, address them by first name
- End the email_body with exactly this footer (no modifications):

// ⚠️  Copy the "Email footer" value from CONSTANTS.md → Section 2
PROMPT;
    }

    public function buildFollowUpPrompt(Company $company, int $daysSince): string
    {
        return <<<PROMPT
Generate a brief, friendly follow-up message from Abdullah Mohamed.
He sent an initial outreach {$daysSince} days ago to {$company->name} but hasn't heard back.

The follow-up should be 2-3 sentences maximum. Not pushy. Just a gentle check-in.
Do NOT repeat his full portfolio. Just ask if they had a chance to review his previous message.

Return ONLY valid JSON:
{
  "email_subject": "...",
  "email_body": "...",
  "whatsapp_message": "..."
}
PROMPT;
    }
}
```

---

## Step 4 — MessageGenerator.php

Create `app/Services/AI/MessageGenerator.php`:

```php
<?php

namespace App\Services\AI;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\OutreachMessage;
use App\Services\AI\Prompts\MessagePromptBuilder;
use Illuminate\Support\Facades\Log;

class MessageGenerator
{
    private GeminiClient $client;
    private MessagePromptBuilder $builder;

    public function __construct()
    {
        $this->client  = new GeminiClient();
        $this->builder = new MessagePromptBuilder();
    }

    /**
     * Generate and persist an outreach message for the given contact/campaign.
     */
    public function generate(Campaign $campaign, Contact $contact): OutreachMessage
    {
        $company      = $contact->company;
        $systemPrompt = $this->builder->buildSystemPrompt();
        $userPrompt   = $this->builder->buildUserPrompt($company, $contact);

        $parsed = $this->callWithRetry($systemPrompt, $userPrompt);

        // If still no valid parse, use fallback
        if (! $this->isValidResponse($parsed)) {
            Log::warning("Gemini returned invalid JSON for company #{$company->id}. Using fallback.");
            $parsed = $this->fallbackMessage($company->name);
        }

        return OutreachMessage::create([
            'campaign_id'      => $campaign->id,
            'contact_id'       => $contact->id,
            'company_id'       => $company->id,
            'email_subject'    => $parsed['email_subject'] ?? 'Reaching out — Backend Developer',
            'email_body'       => $parsed['email_body'] ?? '',
            'whatsapp_message' => $parsed['whatsapp_message'] ?? '',
            'ai_model'         => 'gemini-flash-latest',
            'generated_at'     => now(),
        ]);
    }

    /**
     * Generate a preview (no DB persist). Returns raw array.
     */
    public function preview(Contact $contact): array
    {
        $company      = $contact->company;
        $systemPrompt = $this->builder->buildSystemPrompt();
        $userPrompt   = $this->builder->buildUserPrompt($company, $contact);

        $parsed = $this->callWithRetry($systemPrompt, $userPrompt);

        return $this->isValidResponse($parsed) ? $parsed : $this->fallbackMessage($company->name);
    }

    private function callWithRetry(string $systemPrompt, string $userPrompt): ?array
    {
        // First attempt at temperature 0.8
        $raw    = $this->client->generate($systemPrompt, $userPrompt, 0.8);
        $parsed = $this->parseJson($raw);

        if ($this->isValidResponse($parsed)) {
            return $parsed;
        }

        // Retry once at lower temperature for stricter JSON output
        $raw    = $this->client->generate($systemPrompt, $userPrompt, 0.4);
        $parsed = $this->parseJson($raw);

        return $parsed;
    }

    private function parseJson(string $raw): ?array
    {
        // Gemini sometimes wraps JSON in ```json ... ``` — strip it
        $clean = preg_replace('/```json\s*/i', '', $raw);
        $clean = preg_replace('/```/', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function isValidResponse(?array $parsed): bool
    {
        return $parsed !== null
            && isset($parsed['email_subject'], $parsed['email_body'], $parsed['whatsapp_message'])
            && strlen($parsed['email_body']) > 100
            && strlen($parsed['whatsapp_message']) > 30
            && strlen($parsed['email_subject']) > 5;
    }

    private function fallbackMessage(string $companyName): array
    {
        // ⚠️  Copy fallback text from CONSTANTS.md → Section 4
        // Replace {company_name} placeholder with the $companyName variable.
        return [
            'email_subject'    => '[COPY FROM CONSTANTS.md §4 — fallback email_subject]',
            'email_body'       => "[COPY FROM CONSTANTS.md §4 — fallback email_body. Replace {company_name} with {$companyName}]",
            'whatsapp_message' => '[COPY FROM CONSTANTS.md §4 — fallback whatsapp_message]',
        ];
    }
}
```

---

## Step 5 — GenerateMessageJob

```bash
php artisan make:job GenerateMessageJob
```

`app/Jobs/GenerateMessageJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\OutreachLog;
use App\Services\AI\MessageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly Contact  $contact,
    ) {
        $this->onQueue('generation');
    }

    public function handle(): void
    {
        try {
            $message = (new MessageGenerator())->generate($this->campaign, $this->contact);

            // Queue send jobs for each channel enabled in the campaign
            if (in_array('email', $this->campaign->channels) && $this->contact->email) {
                $log = OutreachLog::create([
                    'campaign_id' => $this->campaign->id,
                    'contact_id'  => $this->contact->id,
                    'company_id'  => $this->contact->company_id,
                    'message_id'  => $message->id,
                    'channel'     => 'email',
                    'status'      => 'pending',
                ]);
                SendEmailJob::dispatch($log)->onQueue('sending');
            }

            if (in_array('whatsapp', $this->campaign->channels) && $this->contact->phone) {
                $log = OutreachLog::create([
                    'campaign_id' => $this->campaign->id,
                    'contact_id'  => $this->contact->id,
                    'company_id'  => $this->contact->company_id,
                    'message_id'  => $message->id,
                    'channel'     => 'whatsapp',
                    'status'      => 'pending',
                ]);
                SendWhatsAppJob::dispatch($log)->onQueue('sending')->delay(
                    now()->addSeconds(rand(
                        (int) \App\Models\SystemSetting::get('whatsapp_delay_min', 60),
                        (int) \App\Models\SystemSetting::get('whatsapp_delay_max', 120)
                    ))
                );
            }
        } catch (\Exception $e) {
            Log::error("GenerateMessageJob failed for contact #{$this->contact->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
```

> Note: `SendEmailJob` and `SendWhatsAppJob` are created in Phase 3. Add them as imports then — for now the job will fail to dispatch them, which is fine since the queue is not running yet.

---

## Step 6 — Filament: "Generate Preview" Action on CompanyResource

Add a Filament `Action` to the company table that previews an AI-generated message.

In `CompanyResource.php`, inside `table()` → `->actions([...])`:

```php
Tables\Actions\Action::make('preview_message')
    ->label('AI Preview')
    ->icon('heroicon-o-sparkles')
    ->color('warning')
    ->action(function (Company $record, array $data) {
        // Get the first active contact for this company
        $contact = $record->contacts()->where('is_active', true)->first();

        if (! $contact) {
            \Filament\Notifications\Notification::make()
                ->title('No contacts found for this company.')
                ->warning()
                ->send();
            return;
        }

        $preview = (new \App\Services\AI\MessageGenerator())->preview($contact);

        // Store in session to display in modal
        session(['ai_preview' => $preview]);
    })
    ->modalHeading('AI Message Preview')
    ->modalContent(fn () => view('filament.ai-preview-modal', [
        'preview' => session('ai_preview', []),
    ]))
    ->modalSubmitActionLabel('Close')
    ->requiresConfirmation(false),
```

Create the modal view at `resources/views/filament/ai-preview-modal.blade.php`:

```blade
<div class="space-y-4">
    <div>
        <strong>Subject:</strong>
        <p class="mt-1 text-sm">{{ $preview['email_subject'] ?? '—' }}</p>
    </div>
    <div>
        <strong>Email Body:</strong>
        <pre class="mt-1 text-sm whitespace-pre-wrap bg-gray-50 p-3 rounded">{{ $preview['email_body'] ?? '—' }}</pre>
    </div>
    <div>
        <strong>WhatsApp Message:</strong>
        <pre class="mt-1 text-sm whitespace-pre-wrap bg-gray-50 p-3 rounded">{{ $preview['whatsapp_message'] ?? '—' }}</pre>
    </div>
</div>
```

---

## Step 7 — OutreachMessageResource (read-only view)

```bash
php artisan make:filament-resource OutreachMessage --generate
```

Make it read-only (remove create/edit/delete). Table columns:

```php
Tables\Columns\TextColumn::make('company.name')->label('Company')->searchable(),
Tables\Columns\TextColumn::make('contact.email')->label('Contact'),
Tables\Columns\TextColumn::make('email_subject')->limit(50),
Tables\Columns\TextColumn::make('ai_model'),
Tables\Columns\TextColumn::make('generated_at')->dateTime()->sortable(),
Tables\Columns\IconColumn::make('approved')->boolean(),
```

Add a view page that shows the full `email_body` and `whatsapp_message` as read-only text areas.

---

## Step 8 — Store Gemini API Key via Filament Settings

Until Phase 6 adds a proper settings page, store the key via Tinker:

```bash
php artisan tinker --execute '\App\Models\SystemSetting::set("gemini_api_key", "YOUR_KEY_HERE");'
```

Get a free Gemini API key at: https://aistudio.google.com/app/apikey

---

## Deliverable

- [ ] `GeminiClient`, `MessagePromptBuilder`, `MessageGenerator` all created
- [ ] `GenerateMessageJob` created on `generation` queue
- [ ] Click "AI Preview" on a company with a contact → modal shows email + WhatsApp message
- [ ] `OutreachMessageResource` visible in Filament sidebar
- [ ] Gemini API key stored in `system_settings`

**Verification test** (run in Tinker):
```bash
php artisan tinker --execute '
$contact = \App\Models\Contact::with("company")->first();
$preview = (new \App\Services\AI\MessageGenerator())->preview($contact);
echo $preview["email_subject"];
'
```

**Next**: Phase 3 — Sending Engine (Email via SMTP + WhatsApp via Evolution API)
