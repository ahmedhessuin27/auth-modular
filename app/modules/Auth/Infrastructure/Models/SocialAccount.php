<?php

namespace App\Modules\Auth\Infrastructure\Models;

use App\Models\User;
use App\Modules\Auth\Domain\Enums\SocialProvider;
use Database\Factories\Modules\Auth\Infrastructure\Models\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Google / Apple account a user signs in with. A user can link several.
 */
#[Fillable(['user_id', 'provider', 'provider_user_id'])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
