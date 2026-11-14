<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUserAccount
{
    /** Sürücü hesabının erişebildiği uçlar (aktif iş, teslim, konum, oturum, bildirim) */
    private const DRIVER_ALLOWED = [
        'api/auth/check',
        'api/auth/user-location',
        'api/vehicle/active-jobs',
        'api/vehicle/orders/*/start',
        'api/vehicle/orders/*/finish-drive',
        'api/vehicle/location',
        'api/cars/my',
        'api/broadcasting/auth',
        'api/notifications/*',
    ];

    private function driverMayAccess(Request $request): bool
    {
        return $request->is(...self::DRIVER_ALLOWED);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ((string) $user->status === UserStatus::ACTIVE->value) {
            if ((string) $user->type === 'driver' && ! $this->driverMayAccess($request)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Sürücü hesabı yalnızca kendisine verilen aktif işi görüntüleyebilir.',
                    'content' => ['driver_restricted' => true],
                    'errors' => [],
                ], 403);
            }

            return $next($request);
        }

        $user->terminateAllSessions();

        return response()->json([
            'status' => false,
            'message' => 'Hesabınız aktif değil. Oturumunuz sonlandırıldı.',
            'content' => [
                'account_inactive' => true,
                'user_status' => (string) $user->status,
            ],
            'errors' => [],
        ], 401);
    }
}
