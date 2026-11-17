<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response([
        "message" => "Bimilyonucu system running",
        "code" => 200,
    ]);
});