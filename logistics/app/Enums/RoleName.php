<?php

namespace App\Enums;

enum RoleName: string
{
    case VEHICLE_OWNER = 'vehicle_owner';
    case CARGO_OWNER = 'cargo_owner';
    case ADMIN = 'admin';

    /**
     * Get all enum values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
