<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function cities(): JsonResponse
    {
        return response()->json([
            'cities' => City::orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function districts(City $city): JsonResponse
    {
        return response()->json([
            'districts' => $city->districts()->orderBy('id')->get(['id', 'name']),
        ]);
    }
}
