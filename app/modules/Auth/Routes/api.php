<?php

use App\Modules\Auth\Presentation\Http\Controllers\AuthController;
use App\Modules\Auth\Presentation\Http\Controllers\PasswordResetController;
use App\Modules\Auth\Presentation\Http\Controllers\SocialLoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Module — API Routes
|--------------------------------------------------------------------------
|
| Loaded by App\Modules\Auth\Providers\AuthServiceProvider with the "api"
| middleware group and an "api" URL prefix, so a route defined as
| "v1/auth/login" here is reachable at "/api/v1/auth/login".
|
*/

Route::prefix('v1/auth')->name('api.auth.')->group(function () {
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('register/resend-code', [AuthController::class, 'resendRegistrationCode'])->name('register.resend-code');
        Route::post('register/verify', [AuthController::class, 'verifyRegistration'])->name('register.verify');
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::post('forgot-password', [PasswordResetController::class, 'sendCode'])->name('forgot-password');
        Route::post('forgot-password/resend-code', [PasswordResetController::class, 'resendCode'])->name('forgot-password.resend-code');
        Route::post('forgot-password/verify', [PasswordResetController::class, 'verifyCode'])->name('forgot-password.verify');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('reset-password');

        // {provider} binds to the SocialProvider enum; anything else is a 404.
        Route::post('social/{provider}', SocialLoginController::class)->name('social');
    });

    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
});
