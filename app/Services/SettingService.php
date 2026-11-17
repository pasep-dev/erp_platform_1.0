<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SettingService
{
    private const CACHE_PREFIX = 'erp.setting.';

    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        return Cache::rememberForever(
            self::CACHE_PREFIX.$key,
            function () use ($key, $default) {
                $setting = Setting::where('key', $key)->first();

                if (! $setting) {
                    return $default;
                }

                $value = $setting->value;

                if ($setting->is_encrypted && $value !== null) {
                    $value = Crypt::decryptString($value);
                }

                return $this->castValue(
                    $value,
                    $setting->type,
                );
            },
        );
    }

    public function set(
        string $key,
        mixed $value,
        string $type = 'string',
        string $group = 'system',
        ?string $description = null,
        bool $isPublic = false,
        bool $isEncrypted = false,
    ): Setting {
        $storedValue = $this->serializeValue(
            $value,
            $type,
        );

        if ($isEncrypted && $storedValue !== null) {
            $storedValue = Crypt::encryptString($storedValue);
        }

        $setting = Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'group' => $group,
                'description' => $description,
                'is_public' => $isPublic,
                'is_encrypted' => $isEncrypted,
            ],
        );

        Cache::forget(self::CACHE_PREFIX.$key);

        return $setting;
    }

    public function forget(string $key): void
    {
        Setting::query()
            ->where('key', $key)
            ->get()
            ->each
            ->delete();

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    public function allPublic(): array
    {
        return Setting::query()
            ->where('is_public', true)
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->mapWithKeys(function (Setting $setting): array {
                $value = $setting->value;

                if ($setting->is_encrypted) {
                    return [];
                }

                return [
                    $setting->key => $this->castValue(
                        $value,
                        $setting->type,
                    ),
                ];
            })
            ->all();
    }

    private function castValue(
        ?string $value,
        string $type,
    ): mixed {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var(
                $value,
                FILTER_VALIDATE_BOOLEAN,
            ),
            'float' => (float) $value,
            'json' => json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
            default => $value,
        };
    }

    private function serializeValue(
        mixed $value,
        string $type,
    ): ?string {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'integer' => (string) $value,
            'float' => (string) $value,
            'json' => json_encode(
                $value,
                JSON_THROW_ON_ERROR,
            ),
            default => (string) $value,
        };
    }
}
