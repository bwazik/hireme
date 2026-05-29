<?php

namespace App\Services\AI;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient
{
    private string $apiKey;
    private string $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = SystemSetting::get('gemini_api_key')
            ?: config('services.gemini.key', '');
    }

    /**
     * Generate content using the given Gemini model.
     *
     * @throws \RuntimeException
     *   code 429  + message containing 'rpm'  → per-minute rate limit hit
     *   code 429  + message containing 'rpd'  → daily quota exhausted
     *   other codes                            → hard failure
     */
    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.8, string $model = 'gemini-2.0-flash'): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API key is not configured.', 0);
        }

        $url = $this->endpoint . $model . ':generateContent';

        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type'   => 'application/json',
                'X-goog-api-key' => $this->apiKey,
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

        if ($response->status() === 429) {
            $body = $response->body();

            // Detect whether this is an RPM (per-minute) or RPD (per-day) quota hit
            // by inspecting the quota metric name returned by Google
            $isRpmLimit = str_contains($body, 'per_minute')
                || str_contains($body, 'per-minute')
                || str_contains($body, 'requests_per_minute')
                || str_contains($body, 'minutely');

            $limitType = $isRpmLimit ? 'rpm' : 'rpd';
            Log::warning("Gemini [{$model}] 429 — {$limitType} limit hit.");

            // Embed the limit type in the message so AiModelRouter can read it
            throw new \RuntimeException("Gemini API 429:{$limitType}", 429);
        }

        if ($response->failed()) {
            Log::error('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Gemini API returned HTTP ' . $response->status(), $response->status());
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }
}
