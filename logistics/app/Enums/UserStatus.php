<?php

namespace App\Enums;

enum UserStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    /**
     * Get all enum values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
