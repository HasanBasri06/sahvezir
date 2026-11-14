<?php

namespace App\Services;

use App\Models\ShipmentOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Araç sahibinin işi "İşi Başlat" ile başlatması: başlama anı kaydedilir, yük sahibine "yola çıktı" bildirimi gider.
 * Aktif iş ekranı yalnızca başlatılmış işleri gösterir.
 */
class JobStartService
{
    /** @return bool İş bu çağrıda başlatıldıysa true (zaten başlatılmışsa false; bildirim yeniden gitmez) */
    public function start(ShipmentOrder $order): bool
    {
        if ($order->started_at) {
            return false;
        }

        $order->update(['started_at' => now()]);
        $shipment = $order->shipment;

        // Açık ekranlar (yük sahibinin ilan detayı, İşlerin Durumu) "yola çıktı" durumuna anında geçsin
        try {
            broadcast(new \App\Events\JobProgressUpdated($order));
        } catch (\Throwable $e) {
            Log::warning('broadcast.job_started.failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        $cargoOwner = User::query()->find((int) $order->creater_user);
        if ($cargoOwner && $shipment) {
            try {
                $route = trim("{$shipment->f_where_city} -> {$shipment->t_where_city}");
                app(ExpoPushService::class)->sendToUser(
                    $cargoOwner,
                    'İlanınız yola çıkmıştır',
                    "{$route} ilanınızın aracı yola çıktı. Yükünüzün nerede olduğunu ilan detayından canlı takip edebilirsiniz.",
                    [
                        'type' => 'drive_started',
                        'shipment_id' => (string) $shipment->id,
                        'slug' => (string) $shipment->slug,
                    ],
                    ['shipment_id' => $shipment->id, 'vehicle_owner_id' => (int) $order->buyer_user]
                );
            } catch (\Throwable $e) {
                Log::warning('push.drive_started.failed', ['shipment_id' => $shipment->id, 'error' => $e->getMessage()]);
            }
        }

        return true;
    }

    /**
     * Araç sahibi zaten yoldayken (başlatılmış aktif işi varken) aldığı bugünkü / tarihi geçmiş iş,
     * yol üstünden alınan ek yük sayılır ve kendiliğinden başlar (dorsede ve ilerleme çubuğunda hemen görünür).
     * İleri tarihli iş "Onaylanan İşler"de bekler.
     */
    public function autoStartIfOnTheRoad(ShipmentOrder $order): void
    {
        $order->loadMissing('shipment');
        $shipment = $order->shipment;
        if (! $shipment || $order->started_at) {
            return;
        }

        $date = $shipment->departure_date;
        if ($date && $date->copy()->startOfDay()->isAfter(now()->startOfDay())) {
            return;
        }

        $onTheRoad = ShipmentOrder::query()
            ->where('buyer_user', $order->buyer_user)
            ->where('id', '!=', $order->id)
            ->whereNotNull('started_at')
            ->whereHas('shipment', fn ($q) => $q->where('status', 'accepted'))
            ->exists();

        if ($onTheRoad) {
            $this->start($order);
        }
    }
}
