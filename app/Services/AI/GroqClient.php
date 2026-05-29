<?php

namespace App\Services\AI;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqClient
{
    private string $apiKey;
    private string $endpoint = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = SystemSetting::get('groq_api_key')
            ?: config('services.groq.key', '');
    }

    /**
     * Generate content using the given Groq model.
     *
     * @throws \RuntimeException
     *   code 429 + message 'rpm' → per-minute rate limit hit
     *   code 429 + message 'rpd' → daily quota exhausted
     *   other codes              → hard failure
     */
    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.8, string $model = 'llama-3.3-70b-versatile'): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Groq API key is not configured.', 0);
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])
            ->post($this->endpoint, [
                'model'       => $model,
                'temperature' => $temperature,
                'max_tokens'  => 1500,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

        if ($response->status() === 429) {
            $body = $response->body();

            $isRpmLimit = str_contains(strtolower($body), 'per minute')
                || str_contains(strtolower($body), 'per_minute')
                || str_contains(strtolower($body), 'per-minute')
                || preg_match('/try again in \d+(?:s|ms|m)/i', $body);

            $limitType = $isRpmLimit ? 'rpm' : 'rpd';
            Log::warning("Groq [{$model}] 429 — {$limitType} limit hit.");

            throw new \RuntimeException("Groq API 429:{$limitType}", 429);
        }

        if ($response->failed()) {
            Log::error('Groq API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Groq API returned HTTP ' . $response->status(), $response->status());
        }

        return $response->json('choices.0.message.content', '');
    }
}
