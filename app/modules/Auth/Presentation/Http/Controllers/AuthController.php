<?php

namespace App\Modules\Auth\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Application\Services\UserAuthService;
use App\Modules\Auth\Domain\Exceptions\EmailNotVerifiedException;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Presentation\Http\Requests\LoginRequest;
use App\Modules\Auth\Presentation\Http\Requests\RegisterRequest;
use App\Modules\Auth\Presentation\Http\Requests\ResendCodeRequest;
use App\Modules\Auth\Presentation\Http\Requests\VerifyCodeRequest;
use App\Modules\Auth\Presentation\Http\Resources\AuthenticatedUserResource;
use App\Modules\Auth\Presentation\Http\Resources\VerificationCodeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserAuthService $authService,
    ) {}

    public function register(RegisterRequest $request): VerificationCodeResource
    {
        return VerificationCodeResource::make(
            $this->authService->register($request->toData()),
        );
    }

    public function resendRegistrationCode(ResendCodeRequest $request): VerificationCodeResource
    {
        return VerificationCodeResource::make(
            $this->authService->resendRegistrationCode(
                $request->validated('email'),
                $request->validated('verification_token'),
            ),
        );
    }

    public function verifyRegistration(VerifyCodeRequest $request): JsonResponse
    {
        $authenticated = $this->authService->verifyRegistration($request->toData());

        return AuthenticatedUserResource::make($authenticated)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @throws ValidationException
     */
    public function login(LoginRequest $request): AuthenticatedUserResource
    {
        try {
            $authenticated = $this->authService->login($request->toData());
        } catch (InvalidCredentialsException) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        } catch (EmailNotVerifiedException) {
            throw ValidationException::withMessages([
                'email' => __('Your email address is not verified. Reset your password to verify it.'),
            ]);
        }

        return AuthenticatedUserResource::make($authenticated);
    }

    public function logout(Request $request): Response
    {
        $this->authService->logout($request->user());

        return response()->noContent();
    }
}
