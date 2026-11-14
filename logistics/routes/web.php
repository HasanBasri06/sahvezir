<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response([
        "message" => "System running",
        "code" => 200,
    ]);
});