<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'online',
        'sistema' => 'API Sistema UGEL',
        'version' => '1.0.0',
        'health' => '/api/health-check',
    ]);
});
