<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Site yöneticisi girişi; müşteri ve satıcı hesaplarından tamamen ayrıdır.
 */
class AdminAuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $admin = Admin::where('email', $request->validated('email'))->first();

        if (! $admin || ! Hash::check($request->validated('password'), $admin->password)) {
            throw ValidationException::withMessages(['email' => 'E-posta veya şifre hatalı.']);
        }

        return response()->json([
            'user' => $admin->only(['id', 'name', 'email']),
            'token' => $admin->createToken('admin-panel')->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->only(['id', 'name', 'email'])]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Çıkış yapıldı.']);
    }
}
