<?php

namespace App\Services;

use App\Models\LoadAlarm;
use App\Models\Shipment;
use App\Models\UserCar;
use Illuminate\Support\Facades\Log;

class LoadAlarmPushService
{
    public function __construct(
        private ExpoPushService $expoPush,
    ) {}

    /**
     * Yeni ilan oluşunca: rotası eşleşen aktif yük alarmı olan ve aracı ilana uyan
     * araç sahiplerine push gönderir. Her kullanıcıya ilan başına en fazla bir bildirim.
     */
    public function notifyForShipment(int $shipmentId): void
    {
        $shipment = Shipment::with('shipmentCars')->find($shipmentId);
        if (! $shipment || $shipment->status !== 'active') {
            return;
        }

        $carIds = $shipment->shipmentCars->pluck('car_id')
            ->push($shipment->car_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $baseLog = ['shipment_id' => $shipment->id, 'slug' => $shipment->slug, 'car_ids' => $carIds];

        if ($carIds === []) {
            Log::info('push.load_alarm.skip_no_cars', $baseLog);

            return;
        }

        $alarms = LoadAlarm::query()
            ->where('is_active', true)
            ->where('user_id', '!=', $shipment->creater_id)
            ->where('f_city', $shipment->f_where_city)
            ->where(fn ($q) => $q->whereNull('f_district')->orWhere('f_district', $shipment->f_where_district))
            // Nereye boşsa: bu şehirden çıkan her ilan
            ->where(fn ($q) => $q->whereNull('t_city')->orWhere('t_city', $shipment->t_where_city))
            ->where(fn ($q) => $q->whereNull('t_district')->orWhere('t_district', $shipment->t_where_district))
            ->whereHas('user', fn ($q) => $q->where('type', 'vehicle_owner'))
            ->get();

        if ($alarms->isEmpty()) {
            return;
        }

        // Kişinin kendi araçlarından biri ilanın araç türlerinden biri olmalı
        $suitableUserIds = UserCar::whereIn('user_id', $alarms->pluck('user_id')->unique())
            ->whereIn('car_id', $carIds)
            ->pluck('user_id')
            ->unique()
            ->all();

        $route = trim("{$shipment->f_where_city} → {$shipment->t_where_city}");
        $notified = 0;

        foreach ($alarms->whereIn('user_id', $suitableUserIds)->unique('user_id') as $alarm) {
            $user = $alarm->user;
            if (! $user) {
                continue;
            }

            try {
                $sent = $this->expoPush->sendToUser(
                    $user,
                    'Yük alarmı: yeni ilan',
                    "{$route} rotasında aracınıza uygun yeni bir yük ilanı var.",
                    [
                        'type' => 'load_alarm',
                        'shipment_id' => (string) $shipment->id,
                        'slug' => (string) $shipment->slug,
                        'alarm_id' => (string) $alarm->id,
                    ],
                    array_merge($baseLog, ['alarm_id' => $alarm->id])
                );

                if ($sent) {
                    $notified++;
                    LoadAlarm::where('user_id', $user->id)
                        ->where('id', $alarm->id)
                        ->update(['last_notified_at' => now()]);
                }
            } catch (\Throwable $e) {
                Log::error('push.load_alarm.exception', array_merge($baseLog, [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]));
            }
        }

        Log::info('push.load_alarm.done', array_merge($baseLog, [
            'matched_alarms' => $alarms->count(),
            'suitable_users' => count($suitableUserIds),
            'notified' => $notified,
        ]));
    }
}
