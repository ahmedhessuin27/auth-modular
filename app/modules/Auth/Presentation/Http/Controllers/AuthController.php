<?php

namespace App\Modules\Users\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Application\Services\UserAuthService;
use App\Modules\Users\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Users\Presentation\Http\Requests\LoginRequest;
use App\Modules\Users\Presentation\Http\Requests\RegisterRequest;
use App\Modules\Users\Presentation\Http\Resources\AuthenticatedUserResource;
use App\Modules\Users\Presentation\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserAuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $authenticated = $this->authService->register($request->toData());

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
        }

        return AuthenticatedUserResource::make($authenticated);
    }

    public function logout(Request $request): Response
    {
        $this->authService->logout($request->user());

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
