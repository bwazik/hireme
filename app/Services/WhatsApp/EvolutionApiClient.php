<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiClient
{
    /**
     * Send a WhatsApp text message.
     *
     * @return array{success: bool, http_code: int, body: mixed}
     */
    public function sendText(WhatsappInstance $instance, string $phone, string $message): array
    {
        $apiKey  = $instance->api_key;
        $url     = rtrim($instance->api_url, '/') . '/message/sendText/' . $instance->name;

        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'apikey'       => $apiKey,
            ])->post($url, [
                'number'      => $phone,
                'text'        => $message,
                'delay'       => 1200,      // typing indicator delay (ms)
                'linkPreview' => true,
            ]);

            return [
                'success'   => $response->successful(),
                'http_code' => $response->status(),
                'body'      => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("EvolutionApiClient error: " . $e->getMessage());
            return ['success' => false, 'http_code' => 0, 'body' => $e->getMessage()];
        }
    }

    /**
     * Check if a number exists on WhatsApp.
     */
    public function checkNumber(WhatsappInstance $instance, string $phone): bool
    {
        $apiKey = $instance->api_key;
        $url    = rtrim($instance->api_url, '/') . '/chat/whatsappNumbers/' . $instance->name;

        try {
            $response = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
                'apikey'       => $apiKey,
            ])->post($url, [
                'numbers' => [$phone],
            ]);

            if (! $response->successful()) return false;

            $data = $response->json();
            return isset($data[0]['exists']) && $data[0]['exists'] === true;
        } catch (\Exception $e) {
            Log::error("EvolutionApiClient checkNumber error: " . $e->getMessage());
            return false;
        }
    }
}
