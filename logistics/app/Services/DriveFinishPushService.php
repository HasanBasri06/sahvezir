<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DriveFinishPushService
{
    public function __construct(
        private ExpoPushService $expoPush,
    ) {}

    /** Araç sahibi sürüşü bitirdiğinde yük sahibi + araç sahibine push. */
    public function notifyDriveFinished(Shipment $shipment, User $vehicleOwner, User $cargoOwner): void
    {
        $route = $this->shipmentRouteLabel($shipment);
        $slug = (string) ($shipment->slug ?? '');
        $shipmentId = (string) $shipment->id;

        $baseLog = [
            'shipment_id' => $shipment->id,
            'slug' => $slug,
            'vehicle_owner_id' => $vehicleOwner->id,
            'cargo_owner_id' => $cargoOwner->id,
        ];

        $cargoOwner->loadMissing('pushDevice');
        $this->dispatch(
            $cargoOwner,
            'Hedefe varıldı',
            "{$route} ilanınız hedefe varmıştır.",
            [
                'type' => 'drive_finished_cargo_owner',
                'shipment_id' => $shipmentId,
                'slug' => $slug,
            ],
            array_merge($baseLog, ['recipient' => 'cargo_owner']),
            'push.drive_finished.cargo_owner'
        );

        $vehicleOwner->loadMissing('pushDevice');
        $this->dispatch(
            $vehicleOwner,
            'Yük teslimi tamamlandı',
            "{$route} ilanınız için yük tesliminiz tamamlanmıştır.",
            [
                'type' => 'drive_finished_vehicle_owner',
                'shipment_id' => $shipmentId,
                'slug' => $slug,
            ],
            array_merge($baseLog, ['recipient' => 'vehicle_owner']),
            'push.drive_finished.vehicle_owner'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $logContext
     */
    private function dispatch(
        User $user,
        string $title,
        string $body,
        array $data,
        array $logContext,
        string $logPrefix,
    ): void {
        Log::info("{$logPrefix}.start", array_merge($logContext, [
            'user_id' => $user->id,
            'has_token' => $user->pushDevice !== null,
        ]));

        try {
            $sent = $this->expoPush->sendToUser($user, $title, $body, $data, $logContext);

            if ($sent) {
                Log::info("{$logPrefix}.success", array_merge($logContext, [
                    'user_id' => $user->id,
                    'title' => $title,
                ]));
            } else {
                Log::warning("{$logPrefix}.failed", array_merge($logContext, [
                    'user_id' => $user->id,
                ]));
            }
        } catch (\Throwable $e) {
            Log::error("{$logPrefix}.exception", array_merge($logContext, [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]));
        }
    }

    private function shipmentRouteLabel(Shipment $shipment): string
    {
        $from = trim((string) $shipment->f_where_city);
        $to = trim((string) $shipment->t_where_city);

        if ($from !== '' && $to !== '') {
            return "{$from} -> {$to}";
        }

        return 'İlanınız';
    }
}
