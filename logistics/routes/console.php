<?php

use App\Enums\UserStatus;
use App\Jobs\CloseShipmentJob;
use App\Jobs\EndTrialProcess;
use App\Mail\RoadsideListingsReminderMail;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

Artisan::command('admin:login', function () {
    Admin::create([
        'name' => 'Admin',
        'email' => 'admin@admin.com',
        'password' => Hash::make('123456'),
    ]);
});

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('shipments:close-due', function () {
    Log::info('shipments:close-due started');
    $now = Carbon::now();
    $closed = 0;

    $shouldClose = function (Shipment $shipment, Carbon $now): bool {
        if ($shipment->departure_date || $shipment->departure_date != 'Yükleme tarihi görüşülecek') {
            $closeAt = Carbon::parse($shipment->departure_date)->startOfDay()->addDay();

            return $now->greaterThanOrEqualTo($closeAt);
        }

        $closeAt = $shipment->created_at->copy()->addDays(21);

        return $now->greaterThanOrEqualTo($closeAt);
    };

    Shipment::query()
        ->where('status', 'active')
        ->orderBy('id')
        ->chunkById(100, function ($shipments) use ($now, &$closed, $shouldClose) {
            foreach ($shipments as $shipment) {
                if (! $shouldClose($shipment, $now)) {
                    continue;
                }
                CloseShipmentJob::dispatch($shipment->id);
                $closed++;
            }
        });

    if ($closed > 0) {
        $this->info("Kapatma kuyruğa alındı: {$closed} ilan");
    }

    return \Illuminate\Console\Command::SUCCESS;
})->purpose('Vadesi gelen aktif ilanları kapatır (departure+1 gün veya yayın+21 gün)');


Artisan::command('users:end-payment-date', function () {
    $this->info('users:end-payment-date started');
    $twoDaysFromNow = now()->addDays(2)->toDateString();
    Log::info('twoDaysFromNow: ' . $twoDaysFromNow);

    User::query()
        ->whereHas('subscriptions', fn ($q) => $q->where('type', 'package')->where('status', 'active')->whereDate('ends_at', $twoDaysFromNow))
        ->orderBy('id')
        ->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $this->info($user->first_name . ' ' . $user->last_name);
            }
        });

    $this->info('users:end-payment-date tamamlandı.');
})->purpose('2 gün önce ödeme tarihi gelen kullanıcılar');

Artisan::command('users:end-account', function () {
    Log::info('kullanıcı hesabını sonlandırıyor...', [
        'date' => now()->toDateTimeString(),
    ]);
    $cutoff = now()->subSeconds(60);
    $cutoffEndDate = $cutoff->copy()->subSeconds(5);

    // Aktif paketi bitmiş kullanıcılar (bitiş anı geçmiş)
    User::query()
        ->whereHas('subscriptions', fn ($q) => $q->where('type', 'package')->where('status', 'active')->where('ends_at', '<=', $cutoff))
        ->orderBy('id')
        ->chunkById(100, function ($users) {
            foreach ($users as $user) {
                // Önce aktif paketi kapat: kuyruk gecikse bile bir sonraki çalıştırmada tekrar seçilmez
                app(\App\Services\SubscriptionService::class)->expire($user);
                EndTrialProcess::dispatch($user);
                $this->info('EndTrialProcess dispatch edildi: ' . $user->id);
            }
        });

    Log::info('kullanıcı hesap sonlandırma kuyruğu tamamlandı.');
});

Artisan::command('users:notify-roadside-listings', function () {
    Log::info('users:notify-roadside-listings started');

    $now = now();
    $targetEnd = $now->copy()->subDays(7);
    $targetStart = $targetEnd->copy()->subMinute();
    $sent = 0;

    User::query()
        ->where('type', 'vehicle_owner')
        ->whereNotNull('email')
        ->whereBetween('created_at', [$targetStart, $targetEnd])
        ->orderBy('id')
        ->chunkById(100, function ($users) use (&$sent) {
            foreach ($users as $user) {
                Mail::to($user->email)->send(new RoadsideListingsReminderMail($user));

                $sent++;
            }
        });

    return \Illuminate\Console\Command::SUCCESS;
})->purpose('Vehicle owner kullanicilara 7. gunde yol uzeri ilan bilgilendirme maili gonderir');

/**
 * Otomatik ödeme: bitişi yaklaşan (24 saat) ve talimatı açık paket / araç kayıtlarını PayTR kayıtlı karttan yeniler,
 * süresi dolan araç kayıtlarını kapatır. Saatlik çalıştırılmalı (cron: `php artisan subscriptions:renew`).
 */
Artisan::command('subscriptions:renew', function () {
    $service = app(\App\Services\SubscriptionService::class);
    $stats = $service->renewDue(app(\App\Services\PaytrRecurringService::class));
    $expired = $service->expireVehicles();

    Log::info('subscriptions:renew', $stats + ['expired_vehicles' => $expired]);
    $this->info("Yenilenen: {$stats['charged']}, bekleyen: {$stats['pending']}, başarısız: {$stats['failed']}, kapanan araç: {$expired}");
})->purpose('Otomatik talimatlı abonelikleri kayıtlı karttan yeniler');

\Illuminate\Support\Facades\Schedule::command('subscriptions:renew')->hourly()->withoutOverlapping();
