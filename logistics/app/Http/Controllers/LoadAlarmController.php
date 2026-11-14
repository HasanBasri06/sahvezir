<?php

namespace App\Http\Controllers;

use App\Models\LoadAlarm;
use App\Support\CityNameNormalizer;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;

/** Araç sahibinin yük alarmları (nereden → nereye, aktif / pasif) */
class LoadAlarmController extends Controller
{
    use ResponseTrait;

    /** İstemciler (web `LOAD_ALARM_LIMIT`, mobil `LOAD_ALARM_LIMIT`) aynı sınırı uygular */
    private const MAX_ALARMS = 10;

    public function index(Request $request)
    {
        if ($denied = $this->denyNonVehicleOwner($request)) {
            return $denied;
        }

        $alarms = LoadAlarm::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return $this->success(['alarms' => $alarms]);
    }

    public function store(Request $request)
    {
        if ($denied = $this->denyNonVehicleOwner($request)) {
            return $denied;
        }

        $data = $this->validateRoute($request);

        $userId = $request->user()->id;
        if (LoadAlarm::where('user_id', $userId)->count() >= self::MAX_ALARMS) {
            return $this->error([], 'En fazla '.self::MAX_ALARMS.' yük alarmı oluşturabilirsiniz.', [], 422);
        }

        $alarm = LoadAlarm::create(array_merge($data, [
            'user_id' => $userId,
            'is_active' => $request->boolean('is_active', true),
        ]));

        return $this->success(['alarm' => $alarm], 'Yük alarmı oluşturuldu');
    }

    public function update(Request $request, int $id)
    {
        if ($denied = $this->denyNonVehicleOwner($request)) {
            return $denied;
        }

        $alarm = LoadAlarm::where('user_id', $request->user()->id)->find($id);
        if (! $alarm) {
            return $this->error([], 'Yük alarmı bulunamadı', [], 404);
        }

        $changes = [];
        if ($request->has('f_city') || $request->has('t_city')) {
            $changes = $this->validateRoute($request);
        }
        if ($request->has('is_active')) {
            $changes['is_active'] = $request->boolean('is_active');
        }
        $alarm->update($changes);

        return $this->success(['alarm' => $alarm->fresh()]);
    }

    public function destroy(Request $request, int $id)
    {
        if ($denied = $this->denyNonVehicleOwner($request)) {
            return $denied;
        }

        $deleted = LoadAlarm::where('user_id', $request->user()->id)->where('id', $id)->delete();
        if (! $deleted) {
            return $this->error([], 'Yük alarmı bulunamadı', [], 404);
        }

        return $this->success([], 'Yük alarmı silindi');
    }

    /**
     * Nereden şehri zorunlu; nereden ilçesi ve nereye (şehir + ilçe) isteğe bağlı.
     *
     * @return array{f_city: string, f_district: ?string, t_city: ?string, t_district: ?string}
     */
    private function validateRoute(Request $request): array
    {
        $data = $request->validate([
            'f_city' => 'required|string|max:100',
            'f_district' => 'nullable|string|max:100',
            't_city' => 'nullable|string|max:100',
            't_district' => 'nullable|string|max:100',
        ]);

        $clean = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;
        $tCity = $clean($data['t_city'] ?? null);

        // İlanlar `cities.name` yazımıyla kaydedilir; eşleşme için aynı yazıma hizala
        return [
            'f_city' => CityNameNormalizer::canonicalForFilter($data['f_city']) ?? trim($data['f_city']),
            'f_district' => $clean($data['f_district'] ?? null),
            't_city' => $tCity !== null ? (CityNameNormalizer::canonicalForFilter($tCity) ?? $tCity) : null,
            // Varış şehri yoksa ilçesi de anlamsız
            't_district' => $tCity !== null ? $clean($data['t_district'] ?? null) : null,
        ];
    }

    private function denyNonVehicleOwner(Request $request)
    {
        if ((string) $request->user()->type !== 'vehicle_owner') {
            return $this->error([], 'Yük alarmı yalnızca araç sahibi hesaplarında kullanılabilir', [], 403);
        }

        return null;
    }
}
