<?php

use App\Modules\Users\Presentation\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Users Module — API Routes
|--------------------------------------------------------------------------
|
| Loaded by App\Modules\Users\Providers\UsersServiceProvider with the
| "api" middleware group and an "api" URL prefix, so a route defined as
| "users/index" here is reachable at "/api/users/index".
|
*/

Route::prefix('v1/users')->name('api.users.')->group(function () {
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
    });
});
