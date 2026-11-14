<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddressRequest;
use App\Http\Resources\AddressResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'addresses' => AddressResource::collection($request->user()->addresses()->with(['city', 'district'])->latest('id')->get()),
        ]);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $request->user()->addresses()->create($request->validated());

        return response()->json(['address' => new AddressResource($address->load(['city', 'district']))], 201);
    }
}
