<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\District;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    use ResponseTrait;

    /**
     * Tüm şehirler
     */
    public function cities(Request $request)
    {
        $query = City::orderBy('name');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', $search . '%');
        }

        $cities = $query->get(['id', 'name', 'code']);
        return $this->success($cities);
    }

    /**
     * Şehire göre ilçeler
     */
    public function districts(Request $request, int|string $cityId)
    {
        $districts = District::where('city_id', $cityId)->orderBy('name')->get(['id', 'city_id', 'name']);
        return $this->success($districts);
    }
}
