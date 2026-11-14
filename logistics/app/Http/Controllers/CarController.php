<?php

namespace App\Http\Controllers;

use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use App\Models\Car;
use App\Models\UserCar;
use App\Support\CatalogAssetUrl;
use App\Support\PublicStorageUrl;
use App\Support\UserCarImageUploader;
use Illuminate\Support\Facades\Validator;

class CarController extends Controller
{
    use ResponseTrait;

    private function decorateUserCarImages(Request $request, UserCar $userCar): void
    {
        foreach (['image_one', 'image_two'] as $column) {
            $absolute = PublicStorageUrl::absoluteForRequest($request, $userCar->{$column});
            if ($absolute !== null) {
                $userCar->setAttribute($column, $absolute);
            }
        }
    }

    private function applyUserCarImageUpdates(Request $request, UserCar $userCar, array $data): array
    {
        $updates = [];

        foreach (['image_one', 'image_two'] as $field) {
            $removeKey = 'remove_'.$field;
            if ($request->hasFile($field)) {
                $newPath = UserCarImageUploader::storeOptionalFromRequest($request, $field);
                if ($newPath !== null && $newPath !== $userCar->{$field}) {
                    UserCarImageUploader::delete($userCar->{$field});
                    $updates[$field] = $newPath;
                }
            } elseif (UserCarImageUploader::truthy($data[$removeKey] ?? false)) {
                UserCarImageUploader::delete($userCar->{$field});
                $updates[$field] = null;
            }
        }

        return $updates;
    }

    public function getAll(Request $request) {
        $cars = Car::with('details')
            ->where('status', 'active')
            ->orderBy('order', 'asc')
            ->get();

        $cars->each(function (Car $car) use ($request) {
            $car->setAttribute('image', CatalogAssetUrl::vehicle($request, $car->image));
            $car->details->each(function ($detail) use ($request) {
                $detail->setAttribute('image', CatalogAssetUrl::vehicle($request, $detail->image));
            });
        });

        return $this->success(['cars' => $cars]);
    }
    public function getMyCars(Request $request) {
        return $this->getCarsByUser($request);
    }

    /** Sanctum user_id ile UserCar listesini döner (kullanıcının araçları). */
    public function getCarsByUser(Request $request) {
        $user = $request->user();
        if (! $user) {
            return $this->success(['cars' => []]);
        }
        $carOwnerQuery = (string) $user->type === 'driver'
            ? fn ($q) => $q->whereIn('id', \App\Models\ShipmentOrder::where('driver_user_id', $user->id)->whereNotNull('user_car_id')->pluck('user_car_id'))
            : fn ($q) => $q->where('user_id', $user->id);
        $cars = UserCar::with('car.details', 'carDetail')
            ->where($carOwnerQuery)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        $cars->each(function (UserCar $userCar) use ($request) {
            $this->decorateUserCarImages($request, $userCar);
            // Kasası açık görsel (aktif işte dorse doluluğu): opened_images içinde aynı adla varsa
            $userCar->setAttribute(
                'opened_image',
                \App\Support\CatalogAssetUrl::openedVehicle($request, $userCar->carDetail?->image ?: $userCar->car?->image)
            );
        });

        return $this->success([
            'cars' => $cars,
            // 'no' iken ikinci araç için Çoklu Araç Yönetim moduna geçilmeli
            'is_multi_cargo_owner' => (string) ($user->is_multi_cargo_owner ?: 'no'),
            'can_add_car' => \App\Http\Controllers\AuthController::canAddAnotherCar($user),
            'price_per_vehicle' => \App\Http\Controllers\AuthController::MULTI_VEHICLE_PRICE,
        ]);
    }

    /** Kullanıcının bir aracını günceller (plaka + isteğe bağlı fotoğraflar). */
    public function updateUserCar(Request $request, $id)
    {
        $data = UserCarImageUploader::resolvePayloadInput($request);

        $validator = Validator::make($data, [
            'plaka' => 'required|string|max:20',
            // Eski araçlarda boş olabilir; gönderilirse doğrulanır
            'capacity_kg' => 'sometimes|nullable|integer|min:1|max:60000',
            'volume_m3' => 'sometimes|nullable|numeric|min:0.1|max:200',
            'remove_image_one' => 'nullable|boolean',
            'remove_image_two' => 'nullable|boolean',
        ], [
            'plaka.required' => 'Plaka zorunludur.',
            'capacity_kg.integer' => 'Taşıma kapasitesi tam sayı (kg) olmalıdır.',
            'capacity_kg.min' => 'Taşıma kapasitesi en az 1 kg olmalıdır.',
            'capacity_kg.max' => 'Taşıma kapasitesi en fazla 60.000 kg olabilir.',
            'volume_m3.required' => 'Aracın yük hacmi (m³) zorunludur.',
            'volume_m3.numeric' => 'Yük hacmi sayı olmalıdır.',
            'volume_m3.min' => 'Yük hacmi en az 0,1 m³ olmalıdır.',
            'volume_m3.max' => 'Yük hacmi en fazla 200 m³ olabilir.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors()->toArray(), 422);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Oturum bulunamadı.'], 401);
        }
        $userCar = UserCar::where('user_id', $user->id)->where('id', $id)->first();
        if (! $userCar) {
            return response()->json(['message' => 'Araç bulunamadı.'], 404);
        }

        $userCar->plate = trim((string) $data['plaka']);
        if (array_key_exists('capacity_kg', $data) && $data['capacity_kg'] !== null && $data['capacity_kg'] !== '') {
            $userCar->capacity_kg = (int) $data['capacity_kg'];
        }
        if (array_key_exists('volume_m3', $data) && $data['volume_m3'] !== null && $data['volume_m3'] !== '') {
            $userCar->volume_m3 = round((float) $data['volume_m3'], 2);
        }
        $imageUpdates = $this->applyUserCarImageUpdates($request, $userCar, $data);
        foreach ($imageUpdates as $column => $value) {
            $userCar->{$column} = $value;
        }
        $userCar->save();

        $fresh = $userCar->fresh('car', 'carDetail');
        $this->decorateUserCarImages($request, $fresh);

        return $this->success($fresh, 'Araç güncellendi.', 200);
    }

    /** Kullanıcının bir aracını siler. */
    public function deleteUserCar(Request $request, $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Oturum bulunamadı.'], 401);
        }
        $userCar = UserCar::where('user_id', $user->id)->where('id', $id)->first();
        if (! $userCar) {
            return response()->json(['message' => 'Araç bulunamadı.'], 404);
        }
        UserCarImageUploader::delete($userCar->image_one);
        UserCarImageUploader::delete($userCar->image_two);
        $userCar->delete();
        return $this->success([], 'Araç silindi.', 200);
    }
}
