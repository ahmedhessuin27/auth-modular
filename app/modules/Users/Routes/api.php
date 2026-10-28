<?php

use App\Modules\Users\Presentation\Http\Controllers\ProfileController;
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
| Signing up, in and out lives in the Auth module.
|
*/

Route::prefix('v1/users')->name('api.users.')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [ProfileController::class, 'show'])->name('me');
    });
});
