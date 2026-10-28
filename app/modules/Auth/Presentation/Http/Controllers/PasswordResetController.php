<?php

namespace App\Modules\Auth\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Application\Services\PasswordResetService;
use App\Modules\Auth\Presentation\Http\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Presentation\Http\Requests\ResendCodeRequest;
use App\Modules\Auth\Presentation\Http\Requests\ResetPasswordRequest;
use App\Modules\Auth\Presentation\Http\Requests\VerifyCodeRequest;
use App\Modules\Auth\Presentation\Http\Resources\VerificationCodeResource;
use Illuminate\Http\Response;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function sendCode(ForgotPasswordRequest $request): VerificationCodeResource
    {
        return VerificationCodeResource::make(
            $this->passwordResetService->sendCode($request->validated('email')),
        );
    }

    public function resendCode(ResendCodeRequest $request): VerificationCodeResource
    {
        return VerificationCodeResource::make(
            $this->passwordResetService->resendCode(
                $request->validated('email'),
                $request->validated('verification_token'),
            ),
        );
    }

    public function verifyCode(VerifyCodeRequest $request): Response
    {
        $this->passwordResetService->verifyCode($request->toData());

        return response()->noContent();
    }

    public function reset(ResetPasswordRequest $request): Response
    {
        $this->passwordResetService->reset(
            $request->validated('email'),
            $request->validated('verification_token'),
            $request->validated('password'),
        );

        return response()->noContent();
    }
}
