<?php

namespace App\Services\AI\Prompts;

use App\Models\Company;
use App\Models\Contact;
use App\Models\SystemSetting;

class MessagePromptBuilder
{
    /**
     * Resolve the output language for a company.
     *
     * Priority:
     *   1. Explicit $languageOverride ('arabic' | 'english')  — set by campaign
     *   2. Auto-detect from company country:
     *        EG / Egypt → arabic
     *        Everything else → english
     *
     * @param  Company  $company
     * @param  string   $languageOverride  'auto' | 'arabic' | 'english'
     * @return string   'arabic' | 'english'
     */
    public function resolveLanguage(Company $company, string $languageOverride = 'auto'): string
    {
        if ($languageOverride !== 'auto') {
            return $languageOverride; // campaign-level override wins
        }

        // Check global system setting. Default to '1' (true/on)
        $isAutoLanguageEnabled = filter_var(
            SystemSetting::get('auto_language_enabled', '1'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $isAutoLanguageEnabled) {
            return 'english';
        }

        $country = strtolower(trim($company->country ?? ''));

        return in_array($country, ['eg', 'egypt', 'مصر'], true)
            ? 'arabic'
            : 'english';
    }

    // =========================================================================
    // SYSTEM PROMPT  (who Abdullah is — sent once per API call)
    // =========================================================================

    public function buildSystemPrompt(string $language = 'english'): string
    {
        // ── English version ──────────────────────────────────────────────────
        if ($language === 'english') {
            return <<<'PROMPT'
You are writing professional outreach messages on behalf of Abdullah Mohamed,
a Back-End Software Developer and SaaS founder based in Egypt.

════════════════════════════════════════
WHO IS ABDULLAH
════════════════════════════════════════

He has 3+ years of real, hands-on experience shipping production systems for
real users — not tutorial projects. He is currently seeking employment in any
form: full-time, part-time, freelance, contract, remote, hybrid, or onsite.
He is open to relocating or working remotely for international companies.

STACK (primary):
  PHP · Laravel · TALL Stack (Tailwind, Alpine.js, Livewire) · Filament
  MySQL · REST APIs · AWS S3 · VPS deployment · Git

STACK FLEXIBILITY — CRITICAL:
  Abdullah is NOT locked to PHP or Laravel.
  He is actively studying C# with a strong focus on OOP and software design
  principles, and he is deepening his Computer Science fundamentals through
  CS50 (Harvard), Data Structures & Algorithms in C++, and programming books.
  He is comfortable entering unfamiliar codebases, adapting to different
  languages and frameworks, and thrives when given a technical challenge
  outside his comfort zone. He has worked on bank systems, EdTech platforms,
  fitness apps, and restaurant aggregators — very different domains.

REAL PRODUCTION PROJECTS:
  1. Shattor (shattor.com) — SaaS EdTech platform he FOUNDED.
     Serves 1,000+ students. Features: QR attendance, online & offline exams,
     financial reports, monthly subscription plans, VPS + AWS deployment.
     Built and maintained entirely by Abdullah alone.

  2. GymZ (gymz.shattor.com) — Social fitness PWA.
     TALL stack, geolocation-based partner matching, gamified rewards
     redeemable at real partner gyms. B2B business model.

  3. Request Atlas — Open-source Laravel package.
     Visualises the Laravel request lifecycle as an execution tree.
     Demonstrates deep understanding of framework internals.

  4. Other: Restaurant aggregator (Observer-pattern coupon engine),
     School management system (Zoom integration), Debt collection system
     for banks, E-commerce platform in pure PHP.

FREELANCE: 6+ completed client projects · 5+ clients · 100% positive feedback.

HONEST POSITIONING:
  He does NOT oversell himself as "senior."
  He is a high-potential mid-level developer who ships real things,
  acknowledges he is still deepening his CS fundamentals, and
  positions himself as someone who grows fast and takes ownership.

LINKS:
  GitHub:   https://github.com/bwazik
  LinkedIn: https://linkedin.com/in/abdullahmohamed-swe

════════════════════════════════════════
TONE RULES
════════════════════════════════════════
- Sound like a real, thoughtful person — NOT a recruitment template.
- Never start with "I hope this email finds you well."
- Never say "I am writing to express my interest."
- Be confident about what he has actually built.
- Be honest that he is still growing — do not fake seniority.
- Be concise. Respect the reader's time.
- Show genuine curiosity about the specific company.
- NEVER mention the city "Banha." Say "Egypt" or "Cairo area" if location
  must be referenced.

════════════════════════════════════════
OUTPUT FORMAT — STRICT
════════════════════════════════════════
Return ONLY a valid JSON object with exactly these three keys.
No markdown. No code fences. No extra keys. Just the JSON.

{
  "email_subject": "...",
  "email_body":    "...",
  "whatsapp_message": "..."
}

email_body rules:
  - 180–260 words
  - Use \n\n between paragraphs — never a single wall of text
  - End with exactly this footer (copy it verbatim, no changes):

Portfolio: shattor.com | gymz.shattor.com | career180.shattor.com | github.com/bwazik
LinkedIn: linkedin.com/in/abdullahmohamed-swe

whatsapp_message rules  (IMPORTANT — READ CAREFULLY):
  - This is sent AFTER the email, as a nudge on a second channel.
  - It must be SHORT: 3–5 sentences maximum.
  - Its ONLY job is to tell the recipient "I just sent you an email — please
    check it when you have a moment." Do not repeat the full pitch.
  - It should feel warm and human, not automated.
  - You will receive the sender's email address — include it naturally so
    the recipient knows which inbox to look in.
PROMPT;
        }

        // ── Arabic version ────────────────────────────────────────────────────
        return <<<'PROMPT'
أنت تكتب رسائل تواصل احترافية باسم عبدالله محمد،
مطور Backend وصاحب مشاريع SaaS من مصر.

════════════════════════════════════════
من هو عبدالله
════════════════════════════════════════

خبرة حقيقية أكثر من 3 سنوات في بناء أنظمة إنتاجية فعلية لمستخدمين حقيقيين.
يبحث حالياً عن فرصة عمل بأي شكل: دوام كامل، دوام جزئي، فريلانس، عقد،
ريموت، هايبريد، أو أونسايت. منفتح على الانتقال أو العمل عن بُعد.

الـ Stack الأساسي:
  PHP · Laravel · TALL Stack (Tailwind, Alpine.js, Livewire) · Filament
  MySQL · REST APIs · AWS S3 · VPS · Git

المرونة التقنية — مهم جداً:
  عبدالله مش مقيد بـ PHP أو Laravel فقط.
  بيدرس حالياً C# بتركيز على OOP ومبادئ تصميم البرمجيات،
  وبيقوي أساسياته في علوم الحاسب من خلال CS50 من هارفارد،
  وهياكل البيانات والخوارزميات بـ C++. مريح مع أي Codebase
  غريب ويتكيف سريع مع أي تكنولوجيا جديدة، وبيعتبر التحدي
  التقني فرصة للتعلم مش عقبة.

المشاريع الحقيقية:
  1. Shattor (shattor.com) — منصة EdTech SaaS أسسها عبدالله بنفسه.
     تخدم أكتر من 1000 طالب. فيها: حضور بـ QR، امتحانات أونلاين وأوفلاين،
     تقارير مالية وأكاديمية، باقات اشتراك شهرية، Deploy على VPS + AWS.

  2. GymZ (gymz.shattor.com) — تطبيق PWA للياقة البدنية.
     TALL stack، مطابقة بالموقع الجغرافي، نظام مكافآت ممثلة بخصومات
     حقيقية في الجيمات. نموذج B2B.

  3. Request Atlas — باكدج Laravel مفتوح المصدر.
     بيعرض Request Lifecycle كـ Execution Tree.
     دليل على فهم عميق لـ Laravel من جوه.

  4. أخرى: منصة طلبات مطاعم، نظام إدارة مدارس، نظام تحصيل ديون للبنوك،
     منصة تجارة إلكترونية بـ PHP خالص.

فريلانس: أكتر من 6 مشاريع مكتملة · 5+ عملاء · 100% تقييمات إيجابية.

طريقة تقديم نفسه:
  لا يبالغ في وصف نفسه كـ Senior.
  يقدم نفسه كمطور mid-level واعد بسجل حقيقي،
  صريح إن مازال بيطور أساسياته، وبيتعلم بسرعة ويتحمل المسؤولية.

الروابط:
  GitHub:   https://github.com/bwazik
  LinkedIn: linkedin.com/in/abdullahmohamed-swe

════════════════════════════════════════
قواعد الأسلوب
════════════════════════════════════════
- اكتب بشكل طبيعي وإنساني — مش قالب توظيف رسمي ممل.
- لا تبدأ بـ "أتمنى أن يكون كل شيء بخير".
- كن واثقاً من اللي بناه فعلاً.
- لا تتكلم عن "بنها" إطلاقاً. قل "مصر" أو "منطقة القاهرة".
- لو الشركة مصرية، استخدم لغة ودية وطبيعية.

════════════════════════════════════════
صيغة المخرجات — صارمة
════════════════════════════════════════
ارجع JSON صحيح بالضبط بهذه المفاتيح الثلاثة.
لا Markdown، لا أكواد، لا مفاتيح زيادة. JSON بس.

{
  "email_subject": "...",
  "email_body":    "...",
  "whatsapp_message": "..."
}

قواعد email_body:
  - 180–260 كلمة
  - استخدم \n\n بين الفقرات — لا تكتب جداراً واحداً من النص
  - انهِ بهذا الـ Footer حرفياً (لا تعدل فيه):

Portfolio: shattor.com | gymz.shattor.com | career180.shattor.com | github.com/bwazik
LinkedIn: linkedin.com/in/abdullahmohamed-swe

قواعد whatsapp_message (مهم — اقرأ بعناية):
  - هذه الرسالة تُرسل بعد الإيميل كتذكير على قناة ثانية.
  - يجب أن تكون قصيرة: 3–5 جمل كحد أقصى.
  - وظيفتها الوحيدة: إخبار المستلم "بعتلك إيميل — ياريت تشوفه لما تقدر."
  - لا تكرر عرض التعريف الكامل.
  - يجب أن تشعر بالدفء والإنسانية وليست آلية.
  - ستستقبل عنوان إيميل المرسِل — اذكره بشكل طبيعي.
PROMPT;
    }

    // =========================================================================
    // USER PROMPT  (per-company context — changes every call)
    // =========================================================================

    /**
     * @param  Company  $company
     * @param  Contact  $contact
     * @param  string   $senderEmail       The Gmail address being used to send — shown in WA teaser
     * @param  bool     $contactHasEmail   True when email will also be sent to this contact
     * @param  string   $language          'arabic' | 'english'
     */
    public function buildUserPrompt(
        Company $company,
        Contact $contact,
        ?string $senderEmail = null,
        bool $contactHasEmail = true,
        string $language = 'english'
    ): string {
        $senderEmail ??= config('services.gmail.email', 'abdullahmohamed.swe@gmail.com');

        // ── Build company context block ───────────────────────────────────────
        $lines = ["Company name: {$company->name}"];

        if ($company->industry) {
            $industries = is_array($company->industry)
                ? implode(', ', $company->industry)
                : $company->industry;
            $lines[] = "Industry: {$industries}";
        }
        if ($company->city)        { $lines[] = "City: {$company->city}"; }
        if ($company->country)     { $lines[] = "Country: {$company->country}"; }
        if ($company->website)     { $lines[] = "Website: {$company->website}"; }
        if ($company->description) { $lines[] = "About them: {$company->description}"; }
        if ($contact->role)        { $lines[] = "Contact role: {$contact->role}"; }
        if ($contact->name)        { $lines[] = "Contact name: {$contact->name}"; }

        $companyContext = implode("\n", $lines);

        // ── Detect if this company likely uses a non-PHP stack ────────────────
        // This gives the AI a signal to emphasise stack flexibility.
        $isNonPhpStack = $this->likelyNonPhpStack($company);
        $stackNote = $isNonPhpStack
            ? "IMPORTANT: This company may NOT use PHP/Laravel. Strongly emphasise Abdullah's ability to work with ANY backend stack, his C# studies, his adaptability, and his love of technical challenges."
            : "Mention his Laravel/PHP strength naturally, but also note he adapts to any stack.";

        // ── WhatsApp teaser instruction ───────────────────────────────────────
        $whatsappInstruction = $contactHasEmail
            ? "For whatsapp_message: The email is being sent simultaneously from {$senderEmail}. Write a short, warm teaser (3–5 sentences) telling the recipient that Abdullah just sent them an email at {$senderEmail} and he would love it if they had a chance to read it. Do NOT repeat the full pitch."
            : "For whatsapp_message: There is NO email being sent — WhatsApp is the only channel. Write a short but complete standalone pitch (5–7 sentences) introducing Abdullah and what he is looking for.";

        // ── Contact Greeting Instruction ──────────────────────────────────────
        if (filled($contact->name)) {
            $greetingInstructionAr = "- اسم المستلم هو \"{$contact->name}\". خاطبه مباشرة بالاسم الأول (مثل \"مرحباً أحمد،\"). لا تستخدم أي أقواس أو نصوص نائبة.";
            $greetingInstructionEn = "- The recipient's name is \"{$contact->name}\". Address them directly by their first name (e.g. \"Hi Alex,\"). Do NOT use placeholders like [First Name].";
        } else {
            $greetingInstructionAr = "- لا يوجد اسم تواصل محدد. استخدم افتتاحية ترحيبية مهنية ومتنوعة لفريق الشركة (مثل التنويع بين: \"مرحباً فريق {$company->name}،\" أو \"تحية طيبة لفريق {$company->name}،\" أو \"مرحباً بكم في {$company->name}،\" أو \"عزيزي فريق {$company->name}،\"). تجنب تكرار نفس الصيغة دائماً. لا تستخدم أي أقواس أو نصوص نائبة مثل [اسم المستلم].";
            $greetingInstructionEn = "- No contact name is available. Address the company/team using varied, creative, and professional greetings (e.g., vary naturally between \"Hi {$company->name} Team,\", \"Dear {$company->name} Team,\", \"Hello Team at {$company->name},\", \"Greetings {$company->name} Team,\", \"Hi everyone at {$company->name},\"). Avoid repeating the exact same greeting format every time. Do NOT use placeholders like [First Name] or [Hiring Manager].";
        }

        // ── Language-specific instruction block ──────────────────────────────
        if ($language === 'arabic') {
            return <<<PROMPT
اكتب رسالة تواصل شخصية من عبدالله محمد إلى الشركة التالية.
عبدالله بيدور على فرصة عمل — عايز ينضم لفريقهم أو يتعاون معهم كفريلانسر أو بعقد.

اكتب الرسالة بالكامل باللغة العربية الفصحى أو العامية المصرية المهنية.

== الشركة المستهدفة ==
{$companyContext}

== تعليمات ==
- اذكر شيئاً محدداً عن هذه الشركة إن أمكن (مجالهم، موقعهم، اللي بيشتغلوا فيه)
- الإيميل يحس إن عبدالله كتبه بنفسه بعد ما بحث عنهم فعلاً — مش رسالة جماعية
- {$stackNote}
- {$greetingInstructionAr}
- لا تذكر "بنها" إطلاقاً
- {$whatsappInstruction}
PROMPT;
        }

        // English (default)
        return <<<PROMPT
Write a personalised outreach message from Abdullah Mohamed to the following company.
Abdullah is looking for work — he wants to join their team or collaborate as a freelancer/contractor.

== TARGET COMPANY ==
{$companyContext}

== INSTRUCTIONS ==
- Reference something specific about this company if possible (industry, location, what they build)
- The email must feel like Abdullah personally wrote it after researching the company — not a mass blast
- {$stackNote}
- {$greetingInstructionEn}
- Do NOT mention the city "Banha" anywhere
- {$whatsappInstruction}
PROMPT;
    }

    // =========================================================================
    // FOLLOW-UP PROMPT  (7 days after no reply)
    // =========================================================================

    /**
     * @param  Company  $company
     * @param  int      $daysSince
     * @param  string   $language   'arabic' | 'english'
     */
    public function buildFollowUpPrompt(
        Company $company,
        int $daysSince,
        string $language = 'english'
    ): string {

        if ($language === 'arabic') {
            return <<<PROMPT
اكتب رسالة متابعة قصيرة وودية من عبدالله محمد.
أرسل رسالة تعريفية منذ {$daysSince} يوماً إلى {$company->name} ولم يرد أحد.

الرسالة يجب أن تكون 2-3 جمل فقط. غير ملحّة. مجرد تذكير لطيف.
لا تكرر عرض التعريف الكامل. فقط اسأل إن كان لديهم فرصة لمراجعة رسالته السابقة.

ارجع JSON صحيح فقط:
{
  "email_subject": "...",
  "email_body": "...",
  "whatsapp_message": "..."
}
PROMPT;
        }

        return <<<PROMPT
Write a brief, friendly follow-up message from Abdullah Mohamed.
He sent an initial outreach {$daysSince} days ago to {$company->name} but hasn't heard back.

The follow-up must be 2-3 sentences maximum. Not pushy. Just a gentle check-in.
Do NOT repeat his full portfolio or pitch. Just ask if they had a chance to review his previous message.

Return ONLY valid JSON — no markdown, no extra text:
{
  "email_subject": "...",
  "email_body": "...",
  "whatsapp_message": "..."
}
PROMPT;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Guess whether a company likely does NOT use PHP/Laravel,
     * based on description and industry keywords.
     * When true, the prompt will push the AI to emphasise stack flexibility.
     */
    private function likelyNonPhpStack(Company $company): bool
    {
        $industryStr = is_array($company->industry) ? implode(' ', $company->industry) : ($company->industry ?? '');

        $text = strtolower(
            ($company->description ?? '') . ' ' .
            $industryStr . ' ' .
            ($company->name ?? '')
        );

        $nonPhpSignals = [
            'react', 'node', 'nodejs', 'vue', 'angular', 'next.js', 'nextjs',
            'python', 'django', 'flask', 'fastapi',
            'java', 'spring', 'kotlin',
            '.net', 'dotnet', 'c#', 'asp.net',
            'ruby', 'rails',
            'go ', 'golang',
            'rust',
            'mobile', 'ios', 'android', 'flutter', 'react native',
            'machine learning', 'ml ', 'ai ', 'data science',
        ];

        foreach ($nonPhpSignals as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }
}
