<?php

namespace App\Modules\Auth\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Application\Services\SocialAuthService;
use App\Modules\Auth\Domain\Enums\SocialProvider;
use App\Modules\Auth\Presentation\Http\Requests\SocialLoginRequest;
use App\Modules\Auth\Presentation\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SocialLoginController extends Controller
{
    public function __construct(
        private readonly SocialAuthService $socialAuthService,
    ) {}

    /**
     * Responds 201 when the sign-in created a new account, so the app can
     * show its onboarding.
     */
    public function __invoke(SocialLoginRequest $request, SocialProvider $provider): JsonResponse
    {
        $authenticated = $this->socialAuthService->authenticate($request->toData($provider));

        return AuthenticatedUserResource::make($authenticated)
            ->response()
            ->setStatusCode(
                $authenticated->user->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK,
            );
    }
}
