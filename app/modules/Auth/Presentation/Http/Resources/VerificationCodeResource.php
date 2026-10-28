<?php

namespace App\Modules\Auth\Presentation\Http\Resources;

use App\Modules\Auth\Application\DTOs\VerificationCodeStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read VerificationCodeStatus $resource
 */
class VerificationCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'verification_token' => $this->when(
                $this->resource->verificationToken !== null,
                $this->resource->verificationToken,
            ),
            'code_sent' => $this->resource->codeSent,
            'resends_left' => $this->resource->resendsLeft,
            'retry_after' => $this->resource->retryAfter,
        ];
    }
}
