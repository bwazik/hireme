<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'description'])]
class SystemSetting extends Model
{
    public $timestamps = false;

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("system_setting_{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, mixed $value, ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description]
        );
        Cache::forget("system_setting_{$key}");
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (?string $value, array $attributes) {
                if (is_string($attributes['key'] ?? null) && str_ends_with($attributes['key'], '_key') && !empty($value)) {
                    try {
                        return decrypt($value);
                    } catch (\Exception $e) {
                        return $value;
                    }
                }
                return $value;
            },
            set: function (?string $value, array $attributes) {
                if (is_string($attributes['key'] ?? null) && str_ends_with($attributes['key'], '_key') && !empty($value)) {
                    return encrypt($value);
                }
                return $value;
            }
        );
    }
}
