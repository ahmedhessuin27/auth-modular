<?php

namespace App\Modules\Users\Presentation\Http\Resources;

use App\Modules\Users\Application\DTOs\AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuthenticatedUser $resource
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array{user: UserResource, token: string, token_type: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => UserResource::make($this->resource->user),
            'token' => $this->resource->token,
            'token_type' => 'Bearer',
        ];
    }
}
