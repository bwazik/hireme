<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Traits\TruncatableTables;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SystemSettingsSeeder extends Seeder
{
    use TruncatableTables;

    public function run(): void
    {
        $this->truncateTables(['system_settings']);

        $defaults = [
            ['key' => 'gemini_api_key',        'value' => config('services.gemini.key', ''), 'description' => 'Google Gemini API key (X-goog-api-key header)'],
            ['key' => 'groq_api_key',           'value' => config('services.groq.key', ''),   'description' => 'Groq API key — used as fallback when Gemini quotas are exhausted'],
            ['key' => 'serper_api_key',         'value' => config('services.serper.key', ''), 'description' => 'Serper.dev API key (X-API-KEY header)'],
            ['key' => 'whatsapp_delay_min',     'value' => '60',   'description' => 'Min seconds between WA messages'],
            ['key' => 'whatsapp_delay_max',     'value' => '120',  'description' => 'Max seconds between WA messages'],
            ['key' => 'email_cv_path',          'value' => 'cv/Abdullah_Mohamed_CV.pdf', 'description' => 'CV path inside storage/app/'],
            ['key' => 'auto_language_enabled',  'value' => '1',    'description' => 'Toggle Arabic auto-detection for Egypt. 0 = English only.'],
            ['key' => 'discovery_enabled',      'value' => 'true', 'description' => 'Toggle discovery engine'],
            ['key' => 'sending_enabled',        'value' => 'true', 'description' => 'Toggle message sending'],
            ['key' => 'ai_generation_enabled',  'value' => 'true', 'description' => 'Toggle AI generation'],
            ['key' => 'daily_start_hour',       'value' => '9',    'description' => 'No sending before this hour'],
            ['key' => 'daily_end_hour',         'value' => '22',   'description' => 'No sending after this hour'],
            ['key' => 'dry_run',                'value' => 'true', 'description' => 'When true: log messages but do NOT call SMTP or WhatsApp API. Set to false when ready to go live.'],
            ['key' => 'serper_queries_used',    'value' => '0',    'description' => 'Serper.dev usage counter (2500 total free)'],
            ['key' => 'discovery_query_offset', 'value' => '0',    'description' => 'Rotating batch cursor for SerperMapsSource::defaultQueries(). Advances by 15 each daily run.'],

            // AI Model Cascade — Priority 1: Gemini Flash Latest (20 RPD / 5 RPM free tier)
            ['key' => 'ai_model_gemini_flash_latest_enabled',    'value' => 'true',                  'description' => 'Enable Gemini Flash Latest in the AI cascade'],
            ['key' => 'ai_model_gemini_flash_latest_name',       'value' => 'gemini-flash-latest',   'description' => 'Gemini Flash Latest model name (always points to the newest stable Flash)'],
            ['key' => 'ai_model_gemini_flash_latest_rpd',        'value' => '20',                    'description' => 'Daily request limit for Gemini Flash Latest (free tier: 20 RPD, 5 RPM)'],
            ['key' => 'ai_model_gemini_flash_latest_used_today', 'value' => '0',                    'description' => 'Requests used today — reset by ResetDailyCountersJob at midnight'],

            // AI Model Cascade — Priority 2: Gemini 2.5 Flash (20 RPD free tier)
            ['key' => 'ai_model_gemini_25_flash_enabled',       'value' => 'true',               'description' => 'Enable Gemini 2.5 Flash in the AI cascade'],
            ['key' => 'ai_model_gemini_25_flash_name',          'value' => 'gemini-2.5-flash',   'description' => 'Gemini 2.5 Flash model name'],
            ['key' => 'ai_model_gemini_25_flash_rpd',           'value' => '20',                 'description' => 'Daily request limit for Gemini 2.5 Flash'],
            ['key' => 'ai_model_gemini_25_flash_used_today',    'value' => '0',                  'description' => 'Requests used today'],

            // AI Model Cascade — Priority 3: Groq Qwen3 32B (1000 RPD)
            ['key' => 'ai_model_groq_qwen3_32b_enabled',        'value' => 'true',               'description' => 'Enable Groq Qwen3-32B in the AI cascade'],
            ['key' => 'ai_model_groq_qwen3_32b_name',           'value' => 'qwen/qwen3-32b',     'description' => 'Groq Qwen3-32B model name'],
            ['key' => 'ai_model_groq_qwen3_32b_rpd',            'value' => '1000',               'description' => 'Daily request limit for Groq Qwen3-32B'],
            ['key' => 'ai_model_groq_qwen3_32b_used_today',     'value' => '0',                  'description' => 'Requests used today'],

            // AI Model Cascade — Priority 4: Groq LLaMA 3.3 70B (1000 RPD)
            ['key' => 'ai_model_groq_llama33_70b_enabled',      'value' => 'true',               'description' => 'Enable Groq LLaMA 3.3 70B in the AI cascade'],
            ['key' => 'ai_model_groq_llama33_70b_name',         'value' => 'llama-3.3-70b-versatile', 'description' => 'Groq LLaMA 3.3 70B model name'],
            ['key' => 'ai_model_groq_llama33_70b_rpd',          'value' => '1000',               'description' => 'Daily request limit for Groq LLaMA 3.3 70B'],
            ['key' => 'ai_model_groq_llama33_70b_used_today',   'value' => '0',                  'description' => 'Requests used today'],

            // AI Model Cascade — Priority 5: Groq LLaMA 3.1 8B Instant (14400 RPD)
            ['key' => 'ai_model_groq_llama31_8b_enabled',       'value' => 'true',               'description' => 'Enable Groq LLaMA 3.1 8B Instant in the AI cascade'],
            ['key' => 'ai_model_groq_llama31_8b_name',          'value' => 'llama-3.1-8b-instant', 'description' => 'Groq LLaMA 3.1 8B model name'],
            ['key' => 'ai_model_groq_llama31_8b_rpd',           'value' => '14400',              'description' => 'Daily request limit for Groq LLaMA 3.1 8B'],
            ['key' => 'ai_model_groq_llama31_8b_used_today',    'value' => '0',                  'description' => 'Requests used today'],

            // AI Model Cascade — Priority 6: Groq Compound Mini (250 RPD)
            ['key' => 'ai_model_groq_compound_mini_enabled',    'value' => 'true',               'description' => 'Enable Groq Compound Mini in the AI cascade'],
            ['key' => 'ai_model_groq_compound_mini_name',       'value' => 'groq/compound-mini', 'description' => 'Groq Compound Mini model name'],
            ['key' => 'ai_model_groq_compound_mini_rpd',        'value' => '250',                'description' => 'Daily request limit for Groq Compound Mini'],
            ['key' => 'ai_model_groq_compound_mini_used_today', 'value' => '0',                  'description' => 'Requests used today'],

            // AI Model Cascade — Priority 7: Groq GPT-OSS 120B (1000 RPD)
            ['key' => 'ai_model_groq_gpt_oss_120b_enabled',     'value' => 'true',               'description' => 'Enable Groq GPT-OSS 120B in the AI cascade'],
            ['key' => 'ai_model_groq_gpt_oss_120b_name',        'value' => 'openai/gpt-oss-120b','description' => 'Groq GPT-OSS 120B model name'],
            ['key' => 'ai_model_groq_gpt_oss_120b_rpd',         'value' => '1000',               'description' => 'Daily request limit for Groq GPT-OSS 120B'],
            ['key' => 'ai_model_groq_gpt_oss_120b_used_today',  'value' => '0',                  'description' => 'Requests used today'],
        ];

        foreach ($defaults as $row) {
            SystemSetting::set($row['key'], $row['value'], $row['description']);
        }
    }
}
