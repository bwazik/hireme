<?php

namespace App\Services\AI;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

/**
 * AI Model Router
 *
 * Cascades through a prioritized list of AI models.
 * Handles two distinct types of 429 errors differently:
 *
 *   RPM (requests-per-minute) limit:
 *     → Cascade to the next model for THIS job only.
 *     → Do NOT mark the model as day-exhausted — it will recover next minute.
 *
 *   RPD (requests-per-day) limit:
 *     → Mark the model as day-exhausted so ALL future jobs skip it immediately.
 *     → Cascade to the next model.
 *
 * Model priority (top = highest):
 *   1. gemini-flash-latest          (Google — Gemini Flash latest, 20 RPD / 5 RPM)
 *   2. gemini-2.5-flash          (Google — Gemini 2.5 Flash,   20 RPD / 5 RPM)
 *   3. qwen/qwen3-32b            (Groq,  1000 RPD / 60 RPM)
 *   4. llama-3.3-70b-versatile   (Groq,  1000 RPD / 30 RPM)
 *   5. llama-3.1-8b-instant      (Groq, 14400 RPD / 30 RPM)
 *   6. groq/compound-mini        (Groq,   250 RPD / 30 RPM)
 *   7. openai/gpt-oss-120b       (Groq,  1000 RPD / 30 RPM)
 *
 * All models and their settings are fully controllable from the Filament Settings page.
 */
class AiModelRouter
{
    /**
     * Default model definitions: [id, driver, model_id, rpd_limit]
     *
     * @var array<int, array{id: string, driver: string, model: string, rpd: int}>
     */
    private const MODEL_DEFAULTS = [
        ['id' => 'gemini_flash_latest', 'driver' => 'gemini', 'model' => 'gemini-flash-latest',        'rpd' => 20],
        ['id' => 'gemini_25_flash',     'driver' => 'gemini', 'model' => 'gemini-2.5-flash',         'rpd' => 20],
        ['id' => 'groq_qwen3_32b',      'driver' => 'groq',   'model' => 'qwen/qwen3-32b',           'rpd' => 1000],
        ['id' => 'groq_llama33_70b',    'driver' => 'groq',   'model' => 'llama-3.3-70b-versatile',  'rpd' => 1000],
        ['id' => 'groq_llama31_8b',     'driver' => 'groq',   'model' => 'llama-3.1-8b-instant',     'rpd' => 14400],
        ['id' => 'groq_compound_mini',  'driver' => 'groq',   'model' => 'groq/compound-mini',       'rpd' => 250],
        ['id' => 'groq_gpt_oss_120b',   'driver' => 'groq',   'model' => 'openai/gpt-oss-120b',      'rpd' => 1000],
    ];

    private GeminiClient $gemini;
    private GroqClient $groq;

    public function __construct()
    {
        $this->gemini = new GeminiClient();
        $this->groq   = new GroqClient();
    }

    /**
     * Generate text using the first available model in the cascade.
     *
     * @throws \RuntimeException if ALL models are exhausted or unavailable
     */
    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.8): array
    {
        $models = $this->getEnabledModels();

        foreach ($models as $modelDef) {
            $modelId = $modelDef['id'];

            // Skip if this model has already hit its daily (RPD) limit for today
            if ($this->isDailyLimitReached($modelId, $modelDef['rpd'])) {
                Log::info("AiModelRouter: skipping [{$modelDef['model']}] — daily RPD limit reached.");
                continue;
            }

            try {
                $text = $modelDef['driver'] === 'gemini'
                    ? $this->gemini->generate($systemPrompt, $userPrompt, $temperature, $modelDef['model'])
                    : $this->groq->generate($systemPrompt, $userPrompt, $temperature, $modelDef['model']);

                $this->incrementUsage($modelId);

                Log::info("AiModelRouter: generated via [{$modelDef['model']}].");

                return ['text' => $text, 'model' => $modelDef['model']];

            } catch (\RuntimeException $e) {
                if ($e->getCode() === 429) {
                    $this->handle429($modelId, $modelDef['model'], $modelDef['rpd'], $e->getMessage());
                    continue;
                }

                // Non-429: bad key, network, timeout, etc. — cascade silently
                Log::error("AiModelRouter: [{$modelDef['model']}] hard error — " . $e->getMessage());
                continue;
            }
        }

        throw new \RuntimeException('All AI models are exhausted or unavailable. Try again tomorrow or check your API keys.');
    }

    /**
     * Handle a 429 response.
     *
     * RPM hit → just log and cascade. Model stays alive for future jobs.
     * RPD hit → log and cascade. Model stays alive so future job retries ask each model again.
     */
    private function handle429(string $modelId, string $modelName, int $rpdLimit, string $errorMessage): void
    {
        // The clients embed 'rpm' or 'rpd' in the exception message (e.g. "Gemini API 429:rpm")
        $isRpmLimit = str_contains($errorMessage, ':rpm');

        if ($isRpmLimit) {
            // Per-minute rate limit — model will recover in seconds, don't mark as day-dead
            Log::warning("AiModelRouter: [{$modelName}] RPM limit hit — cascading to next model (model stays alive for future jobs).");
        } else {
            // Per-day quota hit — mark the model as day-dead so future jobs skip it immediately today
            Log::warning("AiModelRouter: [{$modelName}] RPD daily quota hit (429) — marking day-exhausted and cascading.");
            $this->markDayExhausted($modelId, $rpdLimit);
        }
    }

    /**
     * Build the ordered list of enabled models from system_settings.
     *
     * @return array<int, array{id: string, driver: string, model: string, rpd: int}>
     */
    private function getEnabledModels(): array
    {
        $enabled = [];

        foreach (self::MODEL_DEFAULTS as $def) {
            $isEnabled = filter_var(
                SystemSetting::get("ai_model_{$def['id']}_enabled", 'true'),
                FILTER_VALIDATE_BOOLEAN
            );

            if (! $isEnabled) {
                continue;
            }

            $modelName = SystemSetting::get("ai_model_{$def['id']}_name", $def['model']);
            $rpdLimit  = (int) SystemSetting::get("ai_model_{$def['id']}_rpd", (string) $def['rpd']);

            $enabled[] = [
                'id'     => $def['id'],
                'driver' => $def['driver'],
                'model'  => $modelName,
                'rpd'    => $rpdLimit,
            ];
        }

        return $enabled;
    }

    /**
     * Check if the model has hit its daily request limit.
     */
    private function isDailyLimitReached(string $modelId, int $rpdLimit): bool
    {
        if ($rpdLimit === 0) {
            return false; // 0 = unlimited
        }

        $used = (int) SystemSetting::get("ai_model_{$modelId}_used_today", '0');

        return $used >= $rpdLimit;
    }

    /**
     * Increment the daily usage counter for the given model.
     */
    private function incrementUsage(string $modelId): void
    {
        $current = (int) SystemSetting::get("ai_model_{$modelId}_used_today", '0');
        SystemSetting::set("ai_model_{$modelId}_used_today", (string) ($current + 1));
    }

    /**
     * Fast-forward the usage counter to the daily limit (RPD exhausted).
     * This causes all future jobs to skip this model until midnight reset.
     */
    private function markDayExhausted(string $modelId, int $rpdLimit): void
    {
        SystemSetting::set("ai_model_{$modelId}_used_today", (string) $rpdLimit);
    }

    /**
     * Reset all daily usage counters. Called by ResetDailyCountersJob at midnight.
     */
    public static function resetDailyCounters(): void
    {
        foreach (self::MODEL_DEFAULTS as $def) {
            SystemSetting::set("ai_model_{$def['id']}_used_today", '0');
        }

        Log::info('AiModelRouter: all daily usage counters reset.');
    }

    /**
     * Return the MODEL_DEFAULTS list (used by Filament settings UI).
     *
     * @return array<int, array{id: string, driver: string, model: string, rpd: int}>
     */
    public static function defaultModels(): array
    {
        return self::MODEL_DEFAULTS;
    }
}
