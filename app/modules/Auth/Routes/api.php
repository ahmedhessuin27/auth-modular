<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function () {
    Route::get('/test', function () {
        return response()->json([
            'message' => 'Auth module is working',
        ]);
    });
});