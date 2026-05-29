# 🌌 Autonomous Outreach System (AOS)

An autonomous, local-first, production-ready Laravel & Filament application designed to help software developers find employment. AOS automatically finds software companies in target locations, scrapes their websites for contact information, generates highly personalized AI outreach pitches tailored to their tech stack, sends them via Email and WhatsApp, and tracks replies—all on autopilot while you sleep.

---

## 💡 How It Works (The Pipeline)

```
[Discovery Engine] 
       │ (Serper.dev Maps Scraper)
       ▼
 [Raw Lead Store] 
       │ (Contact Crawler: scrapes website for emails/phones/names)
       ▼
 [Lead Processor] 
       │ (E.164 Phone Normalization & Strict Domain Deduplication)
       ▼
[Campaign Queue] 
       │ (Targeting filters: Country, City, Industry, Channel)
       ▼
[AI Message Generator] 
       │ (Google Gemini 1.5 Flash: Stack adaptation & custom tone)
       ▼
 ┌─────┴────────────────────────┐
 ▼ (sending queue)              ▼ (sending queue)
[Email Sender]          [WhatsApp Sender]
(Gmail SMTP via PHPMailer) (Evolution API - Self-Hosted)
 └─────┬────────────────────────┘
       ▼
[Response Dashboard & Follow-ups] 
 (Filament Panel + Automated 7-Day Checks)
```

---

## 🛠️ System Prerequisites & Stack

AOS is built on a highly modern, reliable, and lightweight monolith stack.

- **Backend Framework**: Laravel 13, PHP 8.5+
- **Admin & Dashboard**: Filament v5 (TALL Stack)
- **Database**: MySQL (standard)
- **Queue System**: Database driver (`QUEUE_CONNECTION=database` — simple, robust, no Redis needed)
- **AI Personalization Engine**: Google Gemini 1.5 Flash (Free tier: 1,500 requests/day, 15 RPM)
- **Email Channel**: PHPMailer using Gmail SMTP App Passwords
- **WhatsApp Channel**: Self-hosted Evolution API instance
- **Scraping Engine**: Serper.dev API (2,500 free query credits) + Symfony DomCrawler

---

## ⚙️ Step-by-Step Installation

Follow these steps to clone, configure, and boot the system locally.

### 1. Clone & Set Up Dependencies
Clone the repository and install Composer and NPM dependencies:
```bash
# Clone the repository
git clone <repository-url>
cd hireme

# Install backend dependencies
composer install

# Install frontend dependencies
npm install
```

### 2. Configure Environment Variables
Copy the template `.env.example` to `.env`:
```bash
copy .env.example .env
```
Open `.env` and set up your database configuration:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hireme
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Generate Keys & Run Migrations
Run the application key generator, migrate the database, and seed the default system settings:
```bash
# Generate encryption key
php artisan key:generate

# Run database migrations and seed default parameters
php artisan migrate --seed
```

### 4. Build Frontend Assets
Compile the assets with Vite:
```bash
npm run build
```

### 5. Create an Admin Account
Create a Filament superuser to log into the admin dashboard:
```bash
php artisan make:filament-user
```
Follow the prompts to enter your name, email address, and password.

---

## 🧑‍💻 Customizing for Your Developer Profile (The "Make it Yours" Guide)

To use this system as a job seeker, you must customize the AI system prompts, footer links, and stack classification logic to match your identity.

### 1. Update Your Profile & System Prompt
Open [MessagePromptBuilder.php](file:///app/Services/AI/Prompts/MessagePromptBuilder.php) and locate the `buildSystemPrompt()` method. 

You must customize both the **English** and **Arabic** (or other target language) system prompts to reflect your profile:
```php
public function buildSystemPrompt(string $language = 'english'): string
{
    if ($language === 'english') {
        return <<<'PROMPT'
You are writing professional outreach messages on behalf of [YOUR NAME],
a [YOUR TITLE] based in [YOUR COUNTRY].

════════════════════════════════════════
WHO IS [YOUR FIRST NAME]
════════════════════════════════════════
He has [X] years of experience shipping...
His primary stack: [Your Stack, e.g., PHP, Laravel, Vue, React...]
...
PROMPT;
    }
}
```

> [!IMPORTANT]
> Make sure to update the **verbatim email footers** at the bottom of the prompt strings (around line 140 and 230). These footers are appended to every AI-generated message:
> ```text
> Portfolio: yourportfolio.com | github.com/yourusername
> LinkedIn: linkedin.com/in/yourprofile
> ```

### 2. Customize the Stack Adaptability Logic
AOS features smart stack adaptation: if it detects that a company uses a stack different from your primary stack, it instructs Gemini to emphasize your flexibility and fast learning.

In [MessagePromptBuilder.php](file:///app/Services/AI/Prompts/MessagePromptBuilder.php), locate the `likelyNonPhpStack()` helper method. Adjust the keyword list to trigger stack flexibility signals according to your preferences:
```php
private function likelyNonPhpStack(Company $company): bool
{
    $nonPhpSignals = [
        'react', 'node', 'nodejs', 'vue', 'angular', 'next.js',
        'python', 'django', 'flask', 'fastapi', 'java', 'spring',
        '.net', 'c#', 'golang', 'rust', 'flutter', 'ios', 'android'
    ];
    // Add or remove keywords based on your primary language/framework
}
```

### 3. Update Fallback Messages
If the Gemini API fails or returns invalid formatting, AOS will use fallback template messages to ensure campaigns are not blocked. Customize these templates inside `phases/CONSTANTS.md` to serve as your offline reference.

---

## 🔑 Configuring API Keys & External Services

Once you log into the Filament dashboard at `http://localhost/admin` (or your local domain), navigate to the **System Settings** page to configure your keys.

### 1. Google Gemini API (Free Engine)
- Go to [Google AI Studio](https://aistudio.google.com/app/apikey) and create a free API Key.
- Log into the Filament Admin Panel → **System Settings**.
- Set `gemini_api_key` to your API key.
- *Security Note: Your key will be automatically encrypted using Laravel’s standard encryption before being stored in the database.*

### 2. Serper.dev API (Scraper Engine)
- Register at [Serper.dev](https://serper.dev) to get a free API Key (includes 2,500 searches, which lasts ~160 days of daily runs).
- Log into Filament → **System Settings**.
- Set `serper_api_key` to your API key.

### 3. Setup Email SMTP Accounts
AOS rotates outgoing emails through active SMTP accounts.
- In Filament, navigate to the **Email Accounts** page and click **New Email Account**.
- Fill in:
  - **Email**: `yourgmail@gmail.com`
  - **Password**: Your **Gmail App Password** (Generate one at [Google Account App Passwords](https://myaccount.google.com/apppasswords). *Requires 2FA enabled*).
  - **SMTP Host**: `smtp.gmail.com`
  - **Port**: `587`
  - **Encryption**: `tls`
  - **Daily Limit**: `80` (Highly recommended to keep it under 80 to protect domain reputation; Gmail hard limit is 500/day).
- *AOS will automatically encrypt the password.*

### 4. Setup WhatsApp Instances (Evolution API)
If you wish to utilize the WhatsApp channel:
- Deploy the self-hosted [Evolution API](https://github.com/EvolutionAPI/evolution-api).
- Navigate to the **WhatsApp Instances** resource in Filament.
- Add your self-hosted Evolution API **Base URL** and **API Key**.
- Set a conservative **Daily Limit** (e.g. `50` messages/day) to prevent number blocking.

---

## 🎯 Running the System

AOS relies on background queue workers and scheduled cron tasks to automate the outreach loop.

### 1. Running the Queue Worker
Outreach generation, scraping, and sending are handled asynchronously. Run the Laravel queue worker:
```bash
php artisan queue:work --queue=sending,generation,discovery,default --tries=3 --timeout=120
```

*Queue priority order is: `sending` (High) → `generation` (Medium) → `discovery` (Low) → `default` (Fallback).*

### 2. Running the System Scheduler (Production)
To run the automated routine on a live server, set up a standard Laravel Cron job:
```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

The daily automated schedules are:
- **00:01 AM**: `ResetDailyCountersJob` (resets daily sent counters on Gmail/WhatsApp accounts)
- **07:00 AM**: `RunDiscoveryJob` (triggers daily Serper Maps scraping search rotation)
- **10:00 AM**: `ScheduleFollowUpsJob` (checks for unreplied leads sent 7+ days ago and schedules a warm follow-up)
- **Hourly**: `BuildCampaignQueueJob` (scans active campaigns, picks up new leads, and queues them for AI generation)
- **Weekly**: `CleanScrapeResultsJob` (purges old scraping tables to keep database light)

### 3. Dry-Run Safety Feature
By default, the system boots in **Dry-Run mode** (`dry_run = true` in System Settings).
When Dry-Run is active, all API calls to Gemini and Serper are executed, but **emails are logged without being sent** and **WhatsApp calls are skipped**.
- Always verify your campaign queue logs in Dry-Run mode first.
- Once you are comfortable with the generated pitches, go to **System Settings** and set `dry_run` to `false` to go live!

### 4. Manual Mode (Highly Recommended & Safest Approach)
If you prefer complete control and maximum safety, you can operate the pipeline **manually** instead of relying on the automated System Scheduler:
1. **Trigger Discovery Manually**: Start the scraper/discovery loop manually by clicking the discovery trigger/button in the Filament Admin UI.
2. **Review Logs Phase-by-Phase**: Monitor the scraping results and logs directly in the admin panel. Examine the discovered companies, crawled websites, and extracted contact details to filter out false positives before moving forward.
3. **Build the Campaign**: Once the leads are verified, manually create the Campaign and trigger the AI outreach message generation.
4. **Final Check**: Review the AI-generated subject lines, email bodies, and WhatsApp nudges for absolute quality, then initiate the sending process.

---

## 📈 Dashboard & Daily Usage (Filament Panel)

Log into your Filament panel to control the cockpit:

1. **Dashboard Widget Overview**: Shows sent volume, queue health, conversion ratios, and serper query usage tracker.
2. **Reply Inbox**: When a company replies to your email or WhatsApp message, log the reply inside Filament. The contact is instantly marked as `Replied`, excluding them from any future follow-up bumps.
3. **Blacklisting & Deactivating**: If a company requests to opt-out, simple untoggle `Is Active` on the Company or Contact resource, and AOS will purge them from all active queues.

---

## ⚠️ Best Practices & Safety Margins

- **Warm-Up**: Do not start sending 100 emails/day on a brand new Gmail address. Start at 10/day and slowly increase it in the Filament limits.
- **Human Typing Delay**: AOS includes a human-like delay between WhatsApp messages (60–120 seconds by default, configurable in settings). Do not set this lower as it acts as an anti-ban shield.
- **Office Hour Guardrails**: The sending worker checks `daily_start_hour` (9 AM) and `daily_end_hour` (10 PM) before firing. If it is outside this window, jobs wait in the queue until business hours resume.
- **CV Attachment**: Make sure your resume is located at `storage/app/cv/Abdullah_Mohamed_CV.pdf` (or your custom path) so it attaches to outreach emails correctly.

---

## 🤖 Working with AI Coding Assistants (Cursor, Gemini, Copilot)

If you plan to use an AI coding assistant (like Cursor, Gemini, or GitHub Copilot) to help you extend, customize, or maintain this project:
- **Master Plan Blueprint**: Use **[PLAN.md](file:///PLAN.md)** at the root of the project. It contains the master engineering plans, database schemas, and architectural designs for the entire application.
- **Phase Guides**: Use the step-by-step documentation files in the **[phases/](file:///phases/)** directory. Each file focuses on a single module (e.g. Scaffolding, Lead Management, AI Generation, Sending Engine) with deep context and code structure hints.
- *Tip: Simply feed the relevant phase file and the master plan to your AI assistant to give it complete codebase context!*

