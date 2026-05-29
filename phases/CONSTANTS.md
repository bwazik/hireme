# CONSTANTS — Single Source of Truth
> **Every phase file references this file.** When any value here changes, update it ONLY here — not in the phases.

---

## 1. Abdullah's Identity

| Field | Value |
|-------|-------|
| Full Name | Abdullah Mohamed |
| Location | Banha, Egypt |
| Role | Back-End Software Developer & SaaS Founder |
| Experience | 3+ years (freelance + production systems) |
| Availability | Immediate |

---

## 2. Portfolio & Links

```
GitHub:    https://github.com/bwazik
LinkedIn:  https://linkedin.com/in/abdullahmohamed-swe
Shattor:   https://shattor.com
GymZ:      https://gymz.shattor.com
```

**Email footer** (appended to every AI-generated email):
```
Portfolio: shattor.com | gymz.shattor.com | github.com/bwazik
LinkedIn: linkedin.com/in/abdullahmohamed-swe
```

**WhatsApp sign-off:**
```
github.com/bwazik
```

---

## 3. AI System Prompt — Abdullah's Profile Block

> Used verbatim in `app/Services/AI/Prompts/MessagePromptBuilder.php` → `buildSystemPrompt()`.  
> When updating projects or skills, edit here first, then copy into the PHP file.

```
== ABOUT ABDULLAH ==
Abdullah is a Back-End Software Developer and SaaS founder with 3+ years of experience.
He is currently seeking employment: full-time, part-time, freelance, contract, remote, hybrid, or onsite.
He lives in Banha, Egypt, and is open to relocating or working remotely internationally.

His primary stack: PHP, Laravel, TALL Stack (Tailwind, Alpine.js, Laravel, Livewire), Filament, MySQL, AWS S3.
He has built and deployed REAL production systems — not tutorial projects:

1. Shattor (shattor.com): A SaaS EdTech platform he founded, serving 1,000+ students.
   Features: QR attendance, online exams, financial reports, monthly subscriptions, VPS + AWS deployment.

2. GymZ (gymz.shattor.com): A social fitness PWA using TALL Stack with geolocation matching
   and a gamified reward system redeemable at partner gyms.

3. Request Atlas: An open-source Laravel package that visualizes the request lifecycle as an
   execution tree — shows his deep understanding of Laravel internals.

4. Other production projects: Restaurant aggregator with campaign engine, School management system,
   Debt collection system for banks, E-commerce platform.

He has 3+ years of freelance experience with 6+ completed projects and 100% positive feedback.

He is honest: strong practical background, actively strengthening CS fundamentals (CS50, DSA, C++, OOP).
He doesn't oversell himself as a senior — he positions as a high-potential mid-level developer who ships real things.

His GitHub: https://github.com/bwazik
His LinkedIn: https://linkedin.com/in/abdullahmohamed-swe
```

---

## 4. Fallback Messages (when Gemini fails)

**Fallback email_subject:**
```
Technical contribution and backend experience for {company_name}
```

**Fallback email_body:**
```
I’ve been following {company_name}’s growth in the digital transformation space for some time, particularly your ability to scale enterprise solutions across both the Egyptian and international markets. It’s rare to find a software house with that kind of longevity and depth in OSS/BSS and specialized systems. I’m a developer who builds things for real users. Over the last three years, I’ve shipped several production systems, including Shattor—an EdTech SaaS I founded and built from scratch that currently serves over 1,000 students. I also developed Request Atlas, an open-source tool for Laravel internals, and GymZ, a social fitness PWA. My background includes everything from bank debt collection systems to restaurant aggregators. While my recent production work is heavily focused on PHP and the TALL stack, I’m not tied to a single tool. I’m currently deepening my CS fundamentals through Harvard’s CS50 and transitioning into C#. I thrive when jumping into unfamiliar codebases and delivering value quickly. I don't just write code; I take ownership of the products I build, ensuring they handle real-world traffic and business logic reliably. I’d love to discuss how my experience shipping end-to-end products might fit into your current projects at {company_name}, whether as a full-time team member or on a contract basis. I’m based in the Cairo area and ready to contribute immediately.

Portfolio: shattor.com | gymz.shattor.com | career180.shattor.com | github.com/bwazik
LinkedIn: linkedin.com/in/abdullahmohamed-swe
```

**Fallback whatsapp_message:**
```
Hi! I just sent over an email from {sender_email} regarding a potential role or collaboration with {company_name}. I’ve been following your work in enterprise software and wanted to share some of the production systems I've built. I'd love it if you could take a look when you have a moment!
```

---

## 5. External API Details

### Google Gemini Flash

| Field | Value |
|-------|-------|
| Model | `gemini-flash-latest` |
| Endpoint | `https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent` |
| Auth | **Header** `X-goog-api-key: {GEMINI_API_KEY}` (NOT a query param) |
| Free tier | 1,500 req/day, 15 RPM |
| Get key | https://aistudio.google.com/app/apikey |
| ENV var | `GEMINI_API_KEY` (already set in `.env`) |
| Config key | `gemini_api_key` in `system_settings` table |

**Minimal working request:**
```bash
curl "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent" \
  -H 'Content-Type: application/json' \
  -H 'X-goog-api-key: YOUR_KEY' \
  -X POST \
  -d '{"contents": [{"parts": [{"text": "Hello"}]}]}'
```

### Serper.dev

| Field | Value |
|-------|-------|
| Maps endpoint | `POST https://google.serper.dev/maps` |
| Search endpoint | `POST https://google.serper.dev/search` |
| Auth | Header `X-API-KEY: {SERPER_API_KEY}` |
| Body | JSON: `{"q": "query", "gl": "us", "hl": "en"}` |
| Free tier | **2,500 total searches (one-time, not monthly)** |
| Sign up | https://serper.dev |
| ENV var | `SERPER_API_KEY` (already set in `.env`) |
| Config key | `serper_api_key` in `system_settings` table |
| Usage tracker | `serper_queries_used` in `system_settings` |

**Minimal working request:**
```php
$response = Http::withHeaders([
    'X-API-KEY'    => env('SERPER_API_KEY'),
    'Content-Type' => 'application/json',
])->post('https://google.serper.dev/search', [
    'q'  => 'software company Cairo',
    'gl' => 'eg',   // country code lowercase
    'hl' => 'en',
]);
```

### Evolution API (WhatsApp)

| Field | Value |
|-------|-------|
| Base URL | `https://whatsapp.shattor.com` |
| Auth | Header `apikey: {encrypted_key_from_db}` |
| Default instance | `BanhaFade` |
| Send endpoint | `POST /message/sendText/{instance}` |
| Config | Encrypted in `whatsapp_instances` table |

**Minimal working request:**
```php
$data = [
    'number' => '+201098617164',
    'text' => 'Hello',
    'delay' => 1200,          // delay typing indicator in ms
    'linkPreview' => true,
];

$ch = curl_init("https://whatsapp.shattor.com/message/sendText/BanhaFade");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "apikey: YOUR_API_KEY"
]);

$response = curl_exec($ch);
curl_close($ch);
```

### Gmail SMTP

| Field | Value |
|-------|-------|
| Host | `smtp.gmail.com` |
| Port | `587` |
| Encryption | `tls` |
| Auth | App Password (NOT regular Gmail password) |
| ENV var | `GMAIL_APP_PASSWORD` (already set in `.env`) |
| Requirement | 2FA must be enabled on the Google account |
| Generate | myaccount.google.com/apppasswords |
| Config | Stored **encrypted** in `email_accounts` table |

---

## 6. Rate Limits & Sending Rules

### Email

| Limit | Value | Reason |
|-------|-------|--------|
| Per-account daily soft limit | `80` sends/day | Safety margin (Gmail hard limit is 500) |
| Delay between emails | 2–8 seconds | Human-like pacing |
| Send window | 9 AM – 10 PM | Business hours |
| Default campaign limit | 50/day | User-controlled |

### WhatsApp

| Limit | Value | Reason |
|-------|-------|--------|
| Per-instance daily limit | `50` sends/day | Conservative to protect the number |
| Min delay between messages | `60` seconds | Mimics human, avoids ban |
| Max delay between messages | `120` seconds | Mimics human, avoids ban |
| Send window | 9 AM – 10 PM | Business hours |

### Follow-up Rules

- Only **1 follow-up** per contact per campaign
- Follow-up sent only if: no reply AND **7+ days** since first outreach
- If follow-up also gets no reply → contact exhausted, no further outreach

---

## 7. Default System Settings (seeded at startup)

| Key | Default Value | Description |
|-----|--------------|-------------|
| `gemini_api_key` | *(empty)* | Set manually after obtaining key |
| `serper_api_key` | *(empty)* | Set manually after obtaining key |
| `whatsapp_delay_min` | `60` | Min seconds between WA messages |
| `whatsapp_delay_max` | `120` | Max seconds between WA messages |
| `email_cv_path` | `cv/Abdullah_Mohamed_CV.pdf` | Path inside `storage/app/` |
| `discovery_enabled` | `true` | Toggle discovery engine on/off |
| `sending_enabled` | `true` | Toggle all sending on/off |
| `ai_generation_enabled` | `true` | Toggle Gemini generation on/off |
| `daily_start_hour` | `9` | No sending before 9 AM |
| `daily_end_hour` | `22` | No sending after 10 PM |
| `dry_run` | `true` | When `true`: log all sends but skip real SMTP/WhatsApp calls. **Set to `false` before going live.** |
| `serper_queries_used` | `0` | Usage counter (max 2500) |

---

## 8. Scraper User-Agent Strings

Rotate randomly among these to avoid bot detection:

```php
$userAgents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
    'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
];
```

---

## 9. Email Domain Blacklist (false positives to filter)

```php
$emailBlacklist = [
    'example.com', 'test.com', 'domain.com',
    'sentry.io', 'sentry-cdn.com',
    'w3.org', 'schema.org',
    'wordpress.org', 'jquery.com',
    'cloudflare.com', 'googleapis.com',
    'bootstrapcdn.com', 'unpkg.com',
    // Also filter image filenames: @2x, @3x (checked via regex in code)
];
```

---

## 10. Default Serper Discovery Queries

Run daily in rotation. At 15 queries/day, the 2,500 free credits last ~166 days.

```php
// Used in SerperMapsSource::defaultQueries()
$queries = [
    // Egypt — Cairo
    ['query' => 'software development company', 'location' => 'Cairo, Egypt',        'country' => 'EG'],
    ['query' => 'software house',               'location' => 'Cairo, Egypt',        'country' => 'EG'],
    ['query' => 'web development company',      'location' => 'Cairo, Egypt',        'country' => 'EG'],
    ['query' => 'IT company',                   'location' => 'Cairo, Egypt',        'country' => 'EG'],
    ['query' => 'SaaS company',                 'location' => 'Cairo, Egypt',        'country' => 'EG'],
    ['query' => 'tech startup',                 'location' => 'Cairo, Egypt',        'country' => 'EG'],
    // Egypt — Alexandria
    ['query' => 'software company',             'location' => 'Alexandria, Egypt',   'country' => 'EG'],
    ['query' => 'web development',              'location' => 'Alexandria, Egypt',   'country' => 'EG'],
    // Germany
    ['query' => 'software development company', 'location' => 'Berlin, Germany',     'country' => 'DE'],
    ['query' => 'web agency',                   'location' => 'Munich, Germany',     'country' => 'DE'],
    ['query' => 'software house',               'location' => 'Hamburg, Germany',    'country' => 'DE'],
    // USA
    ['query' => 'software company',             'location' => 'New York, USA',       'country' => 'US'],
    ['query' => 'web development agency',       'location' => 'San Francisco, USA',  'country' => 'US'],
    // Switzerland
    ['query' => 'software company',             'location' => 'Zurich, Switzerland', 'country' => 'CH'],
];
```

> **To add new cities/countries**: Add a new row here and re-run `RunDiscoveryJob`.

---

## 11. Scheduler Summary

| Time | Job | Purpose |
|------|-----|---------|
| `00:01` daily | `ResetDailyCountersJob` | Resets `sent_today` on all accounts |
| `07:00` daily | `RunDiscoveryJob` | Serper Maps scraping |
| `10:00` daily | `ScheduleFollowUpsJob` | Queue follow-ups for unreplied contacts |
| Every hour | `BuildCampaignQueueJob` (all active campaigns) | Pick up new eligible contacts |
| Weekly | `CleanScrapeResultsJob` | Purge processed results older than 30 days |

---

## 12. Queue Priority Order

```
1. sending    ← highest priority (email + WhatsApp send jobs)
2. generation ← Gemini generation + campaign building
3. discovery  ← scraping jobs
4. default    ← everything else
```

Start worker:
```bash
php artisan queue:work --queue=sending,generation,discovery,default --tries=3 --timeout=120
```

---

## 13. CV File Location

| Setting | Value |
|---------|-------|
| Stored at | `storage/app/cv/Abdullah_Mohamed_CV.pdf` |
| `system_settings` key | `email_cv_path` |
| Attached to | Every outgoing email automatically |

---

## 14. Industry Classification Keywords

| Label | Keywords that trigger it |
|-------|--------------------------|
| `ai_company` | `ai`, `artificial intelligence`, `machine learning`, ` ml `, `deep learning`, `neural`, `llm`, `gpt` |
| `saas` | `saas`, `platform`, `subscription`, `cloud-based`, `software as a service` |
| `mobile_dev` | `mobile`, `ios`, `android`, ` app `, `flutter`, `react native`, `swift`, `kotlin` |
| `fintech` | `fintech`, `banking`, `payment`, `wallet`, `crypto`, `blockchain`, `financial technology` |
| `e_commerce` | `shopify`, `woocommerce`, `magento`, `e-commerce`, `online store`, `retail tech` |
| `digital_agency` | `digital marketing`, `seo`, `social media`, `branding`, `advertising`, `marketing agency` |
| `web_design` | `web design`, `ui/ux`, `creative`, `design agency`, `frontend`, `user experience`, `user interface` |
| `software_house` | `software`, `development`, `tech`, ` it `, `backend`, `fullstack`, `consulting`, `system integrator`, `programming` |
| `other` | *(fallback)* |

---

## 15. Company Source Values

Used in `companies.source` and `contacts.source` columns:

| Value | Meaning |
|-------|---------|
| `google_maps` | Found via Serper Maps API |
| `serper_search` | Found via Serper web search |
| `indeed` | Found via Indeed job board scraper |
| `wellfound` | Found via Wellfound scraper |
| `linkedin` | Found via LinkedIn (future) |
| `facebook` | Found via Facebook (future) |
| `manual` | Manually entered via Filament UI |
