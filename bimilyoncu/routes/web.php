<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response([
        "message" => "System running",
        "code" => 200,
    ]);
});

Route::get('/users', function () {
    $users = User::query()
        ->select(['id', 'name', 'created_at'])
        ->latest('id')
        ->limit(5)
        ->get()
        ->makeHidden('has_password');

    return response()->json([
        'users' => $users,
        'count' => $users->count(),
    ]);
});