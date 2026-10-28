<?php

namespace App\Modules\Auth\Application\Services;

use App\Modules\Auth\Application\DTOs\VerificationCodeStatus;
use App\Modules\Auth\IntegrationEvents\VerificationCodeIssued;
use App\Modules\Auth\IntegrationEvents\VerificationPurpose;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Emails 4-digit codes and checks them, for any flow that has to prove the
 * user owns an email address. State lives in the cache, per purpose + email.
 *
 * Every start() hands out a new verification token and only the latest one
 * is accepted. Without it, someone who knows a victim's email could start a
 * registration with their own password and wait for the victim to confirm
 * the code that lands in the victim's inbox.
 */
final class VerificationCodeService
{
    private const CODE_TTL_SECONDS = 300;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_SENDS_PER_WINDOW = 4;

    private const SEND_WINDOW_SECONDS = 900;

    private const MAX_WRONG_CODES = 4;

    private const LOCKOUT_SECONDS = 300;

    // Outlives the send window, so waiting for the state to expire can't
    // reset the send limit early.
    private const STATE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly AuthEventPublisher $eventPublisher,
    ) {}

    /**
     * Starts a verification, replacing any pending one for the same email,
     * and emails a code unless one went out less than a minute ago.
     *
     * @param  array<string, mixed>  $payload  Handed back by verify() once the code is confirmed.
     *
     * @throws ValidationException when too many codes were requested
     */
    public function start(VerificationPurpose $purpose, string $email, array $payload = []): VerificationCodeStatus
    {
        $verificationToken = Str::random(64);

        $state = Cache::get($this->stateKey($purpose, $email)) ?? [
            'code' => null,
            'sent_at' => null,
            'sends' => 0,
            'window_started_at' => now()->getTimestamp(),
        ];

        $state['token_hash'] = $this->hashToken($verificationToken);
        $state['payload'] = $payload;

        return $this->send($purpose, $email, $state, $verificationToken);
    }

    /**
     * @throws ValidationException
     */
    public function resend(VerificationPurpose $purpose, string $email, string $verificationToken): VerificationCodeStatus
    {
        return $this->send($purpose, $email, $this->pendingState($purpose, $email, $verificationToken));
    }

    /**
     * Checks the code and, when it matches, ends the verification.
     *
     * @return array<string, mixed> the payload given to start()
     *
     * @throws ValidationException
     */
    public function verify(VerificationPurpose $purpose, string $email, string $verificationToken, string $code): array
    {
        $now = now()->getTimestamp();
        $lockKey = $this->lockKey($purpose, $email);
        $lock = Cache::get($lockKey, ['wrong_codes' => 0, 'locked_until' => null]);

        if ($lock['locked_until'] !== null && $now < $lock['locked_until']) {
            $this->failTooManyWrongCodes($lock['locked_until'] - $now);
        }

        $state = $this->pendingState($purpose, $email, $verificationToken);

        if ($now - $state['sent_at'] >= self::CODE_TTL_SECONDS) {
            throw ValidationException::withMessages([
                'code' => __('The code is invalid or has expired.'),
            ]);
        }

        if (! hash_equals($state['code'], $code)) {
            $this->recordWrongCode($lockKey, $lock, $now);
        }

        Cache::forget($this->stateKey($purpose, $email));
        Cache::forget($lockKey);

        return $state['payload'];
    }

    /**
     * @param  array<string, mixed>  $state
     *
     * @throws ValidationException
     */
    private function send(
        VerificationPurpose $purpose,
        string $email,
        array $state,
        ?string $verificationToken = null,
    ): VerificationCodeStatus {
        $now = now()->getTimestamp();

        if ($now - $state['window_started_at'] >= self::SEND_WINDOW_SECONDS) {
            $state['sends'] = 0;
            $state['window_started_at'] = $now;
        }

        $sinceLastSend = $state['sent_at'] === null ? null : $now - $state['sent_at'];

        if ($sinceLastSend !== null && $sinceLastSend < self::RESEND_COOLDOWN_SECONDS) {
            $this->saveState($purpose, $email, $state);

            return new VerificationCodeStatus(
                codeSent: false,
                resendsLeft: max(0, self::MAX_SENDS_PER_WINDOW - $state['sends']),
                retryAfter: self::RESEND_COOLDOWN_SECONDS - $sinceLastSend,
                verificationToken: $verificationToken,
            );
        }

        if ($state['sends'] >= self::MAX_SENDS_PER_WINDOW) {
            $minutes = (int) ceil(($state['window_started_at'] + self::SEND_WINDOW_SECONDS - $now) / 60);

            throw ValidationException::withMessages([
                'email' => __('Too many codes requested. Try again in :minutes minutes.', ['minutes' => $minutes]),
            ]);
        }

        $state['code'] = $this->generateCode();
        $state['sent_at'] = $now;
        $state['sends']++;

        // Published before saving: if the outbox write fails, no code is
        // left behind that the user never received.
        $this->eventPublisher->verificationCodeIssued(new VerificationCodeIssued(
            email: $email,
            code: $state['code'],
            purpose: $purpose,
            expiresInMinutes: intdiv(self::CODE_TTL_SECONDS, 60),
        ));

        $this->saveState($purpose, $email, $state);

        return new VerificationCodeStatus(
            codeSent: true,
            resendsLeft: self::MAX_SENDS_PER_WINDOW - $state['sends'],
            retryAfter: self::RESEND_COOLDOWN_SECONDS,
            verificationToken: $verificationToken,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException when nothing is pending, or a newer start() replaced it
     */
    private function pendingState(VerificationPurpose $purpose, string $email, string $verificationToken): array
    {
        $state = Cache::get($this->stateKey($purpose, $email));

        if ($state === null || ! hash_equals($state['token_hash'], $this->hashToken($verificationToken))) {
            throw ValidationException::withMessages([
                'verification_token' => __('This verification has expired. Please request a new code.'),
            ]);
        }

        return $state;
    }

    /**
     * @param  array{wrong_codes: int, locked_until: ?int}  $lock
     *
     * @throws ValidationException
     */
    private function recordWrongCode(string $lockKey, array $lock, int $now): never
    {
        $lock['wrong_codes']++;

        if ($lock['wrong_codes'] >= self::MAX_WRONG_CODES) {
            Cache::put(
                $lockKey,
                ['wrong_codes' => 0, 'locked_until' => $now + self::LOCKOUT_SECONDS],
                self::STATE_TTL_SECONDS,
            );

            $this->failTooManyWrongCodes(self::LOCKOUT_SECONDS);
        }

        Cache::put($lockKey, $lock, self::STATE_TTL_SECONDS);

        throw ValidationException::withMessages([
            'code' => __('The code is invalid or has expired.'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function failTooManyWrongCodes(int $secondsLeft): never
    {
        throw ValidationException::withMessages([
            'code' => __('Too many incorrect codes. Try again in :minutes minutes.', [
                'minutes' => (int) ceil($secondsLeft / 60),
            ]),
        ]);
    }

    private function generateCode(): string
    {
        // Fixed outside production, so testers never need a real inbox.
        return app()->isProduction() ? sprintf('%04d', random_int(0, 9999)) : '5555';
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function saveState(VerificationPurpose $purpose, string $email, array $state): void
    {
        Cache::put($this->stateKey($purpose, $email), $state, self::STATE_TTL_SECONDS);
    }

    private function stateKey(VerificationPurpose $purpose, string $email): string
    {
        return "auth.verification.{$purpose->value}.{$email}";
    }

    private function lockKey(VerificationPurpose $purpose, string $email): string
    {
        return "auth.verification.{$purpose->value}.{$email}.lock";
    }

    private function hashToken(string $verificationToken): string
    {
        return hash('sha256', $verificationToken);
    }
}
