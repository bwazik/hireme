<?php

namespace App\Services\AI;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\OutreachLog;
use App\Models\OutreachMessage;
use App\Services\AI\Prompts\MessagePromptBuilder;
use Illuminate\Support\Facades\Log;

class MessageGenerator
{
    private AiModelRouter $router;
    private MessagePromptBuilder $builder;

    public function __construct()
    {
        $this->router  = new AiModelRouter();
        $this->builder = new MessagePromptBuilder();
    }

    /**
     * Generate and persist an outreach message for the given contact/campaign.
     */
    public function generate(
        Campaign $campaign,
        Contact $contact,
        ?string $senderEmail = null,
        string $languageOverride = 'auto'
    ): OutreachMessage {
        $senderEmail ??= config('services.gmail.email', 'abdullahmohamed.swe@gmail.com');
        $company         = $contact->company;
        $language        = $this->builder->resolveLanguage($company, $languageOverride);
        $contactHasEmail = filled($contact->email);
        $contactHasPhone = filled($contact->phone);

        $systemPrompt = $this->builder->buildSystemPrompt($language);
        $userPrompt   = $this->builder->buildUserPrompt($company, $contact, $senderEmail, $contactHasEmail, $language);

        $parsed = $this->callWithRetry($systemPrompt, $userPrompt);

        // If still no valid parse, throw exception so the queue job fails and retries
        if (! $this->isValidResponse($parsed)) {
            throw new \RuntimeException("AI returned invalid JSON or failed to generate for company #{$company->id}. Job will retry.");
        }

        return OutreachMessage::updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'contact_id'  => $contact->id,
            ],
            [
                'company_id'       => $company->id,
                'email_subject'    => $parsed['email_subject'] ?? '',
                'email_body'       => $parsed['email_body'] ?? '',
                'whatsapp_message' => $parsed['whatsapp_message'] ?? '',
                'ai_model'         => $parsed['_model'] ?? 'unknown',
                'generated_at'     => now(),
                'approved'         => true,
            ]
        );
    }

    /**
     * Generate and persist a follow-up message for a contact who hasn't replied.
     */
    public function generateFollowUp(Campaign $campaign, Contact $contact): OutreachMessage
    {
        $company   = $contact->company;
        $sentAt    = OutreachLog::where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->value('sent_at');

        $daysSince = $sentAt ? (int) now()->diffInDays($sentAt) : 7;
        $language  = $this->builder->resolveLanguage($company);

        $systemPrompt = $this->builder->buildSystemPrompt($language);
        $userPrompt   = $this->builder->buildFollowUpPrompt($company, $daysSince);

        $parsed = $this->callWithRetry($systemPrompt, $userPrompt);

        if (! $this->isValidResponse($parsed)) {
            throw new \RuntimeException("AI returned invalid JSON or failed to generate follow-up for company #{$company->id}. Job will retry.");
        }

        return OutreachMessage::create([
            'campaign_id'      => $campaign->id,
            'contact_id'       => $contact->id,
            'company_id'       => $company->id,
            'email_subject'    => $parsed['email_subject'],
            'email_body'       => $parsed['email_body'],
            'whatsapp_message' => $parsed['whatsapp_message'],
            'ai_model'         => $parsed['_model'] ?? 'unknown',
            'generated_at'     => now(),
            'approved'         => true,
        ]);
    }

    /**
     * Generate a preview (no DB persist). Returns raw array.
     */
    public function preview(
        Contact $contact,
        ?string $senderEmail = null,
        string $languageOverride = 'auto'
    ): array {
        $senderEmail ??= config('services.gmail.email', 'abdullahmohamed.swe@gmail.com');
        $company         = $contact->company;
        $language        = $this->builder->resolveLanguage($company, $languageOverride);
        $contactHasEmail = filled($contact->email);

        $systemPrompt = $this->builder->buildSystemPrompt($language);
        $userPrompt   = $this->builder->buildUserPrompt($company, $contact, $senderEmail, $contactHasEmail, $language);

        $parsed = $this->callWithRetry($systemPrompt, $userPrompt);

        if (! $this->isValidResponse($parsed)) {
            throw new \RuntimeException("AI returned invalid JSON or failed to generate preview for company #{$company->id}.");
        }

        return $parsed;
    }

    private function callWithRetry(string $systemPrompt, string $userPrompt): ?array
    {
        // First attempt at temperature 0.82
        $result = $this->router->generate($systemPrompt, $userPrompt, 0.82);
        $parsed = $this->parseJson($result['text']);

        if ($this->isValidResponse($parsed)) {
            $parsed['_model'] = $result['model'];
            return $parsed;
        }

        // Retry once at lower temperature for stricter JSON output
        $result = $this->router->generate($systemPrompt, $userPrompt, 0.4);
        $parsed = $this->parseJson($result['text']);

        if (is_array($parsed)) {
            $parsed['_model'] = $result['model'];
        }

        return $parsed;
    }

    private function parseJson(string $raw): ?array
    {
        // Find the first { and the last } in the raw string to strip any conversational preamble/postamble
        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $jsonString = substr($raw, $start, $end - $start + 1);
            $decoded    = json_decode($jsonString, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function isValidResponse(?array $parsed): bool
    {
        return $parsed !== null
            && isset($parsed['email_subject'], $parsed['email_body'], $parsed['whatsapp_message'])
            && strlen($parsed['email_body']) > 100
            && strlen($parsed['whatsapp_message']) > 20
            && strlen($parsed['email_subject']) > 5;
    }
}
