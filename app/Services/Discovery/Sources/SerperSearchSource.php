<?php

namespace App\Services\Discovery\Sources;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerperSearchSource
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) (SystemSetting::get('serper_api_key') ?? config('services.serper.key') ?? '');
    }

    /**
     * Search Google Web via Serper.dev.
     *
     * @return array  Array of organic results: [{title, link, snippet, ...}]
     */
    public function search(string $query, string $countryCode): array
    {
        if (empty($this->apiKey)) {
            Log::warning('SerperSearchSource: missing API key; search skipped for query: ' . $query);
            return [];
        }

        try {
            $response = Http::timeout(15)->withHeaders([
                'X-API-KEY'    => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://google.serper.dev/search', [
                'q'   => $query,
                'gl'  => strtolower($countryCode),
                'num' => 10,
            ]);
            $used = (int) SystemSetting::get('serper_queries_used', 0);
            SystemSetting::set('serper_queries_used', $used + 1);

            $organic = $response->json('organic', []);

            if (empty($organic)) {
                $snippet = mb_substr($response->body() ?? '', 0, 1000);
                Log::info("SerperSearchSource: empty organic results for query='{$query}' country='{$countryCode}' status={$response->status()} body_snippet=" . $snippet);
            } else {
                Log::info("SerperSearchSource: got " . count($organic) . " organic results for query='{$query}' country='{$countryCode}'");
            }

            return $organic;
        } catch (\Exception $e) {
            Log::error('SerperSearchSource error: ' . $e->getMessage());

            return [];
        }
    }

    public static function defaultQueries(): array
    {
        return [
            ['query' => 'software development company Cairo email contact site:eg', 'country' => 'EG'],
            ['query' => 'web agency Egypt hire Laravel developer',                  'country' => 'EG'],
            ['query' => 'software house Germany "backend developer"',               'country' => 'DE'],
        ];
    }
}
