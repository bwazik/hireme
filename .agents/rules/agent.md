---
trigger: always_on
---

# Agent Onboarding — Autonomous Outreach System

> Read this first. Everything else is linked from here.

---

## What This Project Is

A Laravel + Filament admin system that **autonomously finds software companies, generates AI-personalized outreach messages, and sends them via Email and WhatsApp** — to help Abdullah Mohamed find employment.

Built on top of an existing Laravel 13 / Filament 5 application at `c:\laragon\www\hireme`.

---

## Key Files — Read These Before Writing Any Code

| File                  | Purpose                                                                                                                                            |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| `phases/CONSTANTS.md` | **Single source of truth.** All API URLs, rate limits, Abdullah's profile, portfolio links, fallback messages, default queries. Update here first. |
| `phases/phase-N-*.md` | Step-by-step implementation guides for each phase (0–6). Each is self-contained.                                                                   |
| `PLAN.md`             | Full architecture reference. Use only when a phase file doesn't answer your question.                                                              |

---

## Stack

| Layer     | Technology                                                   |
| --------- | ------------------------------------------------------------ |
| Framework | Laravel 13, PHP 8.5+                                         |
| Admin UI  | Filament 5                                                   |
| Queue     | Database driver (`QUEUE_CONNECTION=database`)                |
| AI        | Google Gemini 1.5 Flash (via HTTP, key in `system_settings`) |
| Email     | PHPMailer → Gmail SMTP                                       |
| WhatsApp  | Evolution API (self-hosted)                                  |
| Scraping  | Serper.dev API + Symfony DomCrawler                          |
| DB        | MySQL                                                        |

---

## Current Implementation Status

Check which phases are done by looking at whether the migration tables and service classes exist:

```bash
php artisan migrate:status          # Are all 10 custom tables present?
php artisan route:list              # Is Filament panel registered?
ls app/Services/                    # Which service layers exist?
```

The phases in order:

- **Phase 0** — Migrations + SystemSetting model + Filament setup
- **Phase 1** — All 10 models + Filament resources + LeadProcessor (dedup)
- **Phase 2** — GeminiClient + MessageGenerator + AI preview in UI
- **Phase 3** — EmailSender + WhatsAppSender + SendEmailJob + SendWhatsAppJob
- **Phase 4** — CampaignResource + BuildCampaignQueueJob + dashboard widgets
- **Phase 5** — SerperMapsSource + ContactExtractor + RunDiscoveryJob
- **Phase 6** — System Settings page + Reply Inbox + CSV export + alerting

---

## Rules

1. **Never hardcode** portfolio links, API URLs, rate limits, or Abdullah's profile text. Everything is in `phases/CONSTANTS.md`.
2. **Passwords and API keys** are encrypted with Laravel's `encrypt()` before saving to DB. Never store raw.
3. **Queue names** in order of priority: `sending` → `generation` → `discovery` → `default`.
4. **Only send** between `daily_start_hour` (9) and `daily_end_hour` (22) — read from `system_settings`.
5. **Deduplication** is enforced on `website_domain` (companies) and `email`/`phone` (contacts). Always use `LeadProcessor::process()` — never insert directly.
6. Run `vendor/bin/pint --dirty` after any PHP file edit.
7. Write a Pest test for any new service or job you create.

---

## How to Run Locally

```bash
# Terminal 1 — web server
php artisan serve

# Terminal 2 — queue worker
php artisan queue:work --queue=sending,generation,discovery,default --tries=3 --timeout=120

# Terminal 3 — scheduler (triggers discovery, follow-ups, counter resets)
php artisan schedule:work
```

---

## Where Things Live

```
app/
├── Models/           — Company, Contact, Campaign, OutreachMessage, OutreachLog,
│                       EmailAccount, WhatsappInstance, ScrapingJob, ScrapingResult, SystemSetting
├── Services/
│   ├── AI/           — GeminiClient, MessageGenerator, Prompts/MessagePromptBuilder
│   ├── Discovery/    — DiscoveryOrchestrator, Sources/, Extractors/, LeadProcessor
│   ├── Email/        — EmailSender, SmtpMailer, AccountSelector
│   ├── WhatsApp/     — WhatsAppSender, EvolutionApiClient
│   └── Campaign/     — CampaignContactSelector
├── Jobs/
│   ├── GenerateMessageJob, SendEmailJob, SendWhatsAppJob
│   ├── BuildCampaignQueueJob, ScheduleFollowUpsJob
│   ├── Discovery/RunDiscoveryJob
│   └── Maintenance/ResetDailyCountersJob, CleanScrapeResultsJob
└── Filament/
    ├── Resources/    — Company, Contact, Campaign, OutreachLog, OutreachMessage,
    │                   EmailAccount, WhatsappInstance, ScrapingJob
    ├── Widgets/      — StatsOverview, CampaignStatus, ReplyInbox, DiscoveryActivity
    └── Pages/        — SystemSettings

phases/               — Phase-by-phase implementation guides (read the relevant one)
storage/app/cv/       — Abdullah_Mohamed_CV.pdf (attached to every email)
```

---

## If You're Stuck

1. Check `phases/CONSTANTS.md` for values/config.
2. Check the relevant `phases/phase-N-*.md` for implementation details.
3. Check `PLAN.md` Section 6 for the full DB schema, Section 8 for module interfaces.
