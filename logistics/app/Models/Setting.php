<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'ios_version',
        'android_version',
    ];

    /** Tek satırlık ayar kaydı (yoksa oluşturur). */
    public static function current(): self
    {
        return static::query()->firstOrCreate(
            [],
            [
                'ios_version' => '1.0.0',
                'android_version' => '1.0.0',
            ]
        );
    }

    public function versionForPlatform(string $platform): string
    {
        $normalized = strtolower(trim($platform));

        return match ($normalized) {
            'ios' => (string) ($this->ios_version ?: '1.0.0'),
            'android' => (string) ($this->android_version ?: '1.0.0'),
            default => (string) ($this->android_version ?: $this->ios_version ?: '1.0.0'),
        };
    }
}
