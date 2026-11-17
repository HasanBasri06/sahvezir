<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response([
        "message" => "Logisic server running",
        "code" => 200,
    ]);
});