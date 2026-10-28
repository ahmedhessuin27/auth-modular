# Auth & Notifications changes

Uncommitted work on `main`, on top of `d67a1e5 feat(auth): implement user authentication with Sanctum`.

## Summary

- **Welcome email:** new users get a welcome email, sent by the Notifications module when it consumes `auth.user.registered` from RabbitMQ.
- **Auth module owns sign-up and sign-in.** Register, login and logout moved from the Users module. The Users module keeps only the profile endpoint.
- **Registration needs a 4-digit email code.** The user account is created only after the code is confirmed.
- **Forgot password** is new: send code → verify code → set a new password.
- **Google and Apple sign-in** are new. The app sends the provider's ID token and the API verifies it.
- **RabbitMQ** now carries two events to the Notifications module:
  - `auth.user.registered` → welcome email
  - `auth.verification_code.issued` → verification code email

  These use the existing outbox → relay → consumer pipeline. There's no new publisher, consumer or queue.
- **New dependency:** `firebase/php-jwt` ^7.2 (v7.2.1), used to verify Google/Apple ID tokens.
- **Tests:** 40 tests, 152 assertions, all passing. Pint passes.

## ⚠️ Breaking API changes

Clients have to be updated for these:

| Before | After |
|---|---|
| `POST /api/v1/users/register` returned a user and a token | `POST /api/v1/auth/register` only emails a code. The client then calls `POST /api/v1/auth/register/verify` to get the user and token. |
| `POST /api/v1/users/login` | `POST /api/v1/auth/login` |
| `POST /api/v1/users/logout` | `POST /api/v1/auth/logout` |
| `GET /api/v1/auth/test` (placeholder) | Removed |
| `GET /api/v1/users/me` | Unchanged |

- **Register no longer takes `device_name`.** Send it to `register/verify` instead.
- **Login rejects unverified emails** with a 422. Accounts created before this change have `email_verified_at = null`. They can verify by going through forgot password, which marks the email verified.

## Endpoints

All endpoints are under `/api/v1`.

| Method & path | Body | Success |
|---|---|---|
| `POST auth/register` | `name`, `email`, `password`, `password_confirmation` | 200, verification status |
| `POST auth/register/resend-code` | `email`, `verification_token` | 200, verification status |
| `POST auth/register/verify` | `email`, `verification_token`, `code`, `device_name`? | 201, user + token |
| `POST auth/login` | `email`, `password`, `device_name`? | 200, user + token |
| `POST auth/logout` 🔒 | none | 204 |
| `POST auth/forgot-password` | `email` | 200, verification status |
| `POST auth/forgot-password/resend-code` | `email`, `verification_token` | 200, verification status |
| `POST auth/forgot-password/verify` | `email`, `verification_token`, `code` | 204 |
| `POST auth/reset-password` | `email`, `verification_token`, `password`, `password_confirmation` | 204 |
| `POST auth/social/{google\|apple}` | `id_token`, `name`?, `device_name`? | 201 for a new user, 200 for an existing one; user + token |
| `GET users/me` 🔒 | none | 200, user |

- 🔒 = needs `Authorization: Bearer <token>`.
- Every `auth/*` route except logout is rate limited to 6 requests per minute.
- Emails are lowercased before validation, so `Ahmed@Example.com` and `ahmed@example.com` are the same account.
- `device_name` defaults to `api`. It's the name stored on the Sanctum token.
- Errors are standard Laravel 422 validation responses (`{"message": ..., "errors": {field: [...]}}`).

**Verification status:**
```json
{
  "data": {
    "verification_token": "64-char string (only on register / forgot-password)",
    "code_sent": true,
    "resends_left": 3,
    "retry_after": 60
  }
}
```

**User + token:**
```json
{
  "data": {
    "user": { "id": 1, "name": "...", "email": "...", "email_verified_at": "...", "created_at": "..." },
    "token": "1|...",
    "token_type": "Bearer"
  }
}
```

## How the flows work

### Registration

1. **`register`:**
   - Validates the request, including that the email isn't already taken.
   - Stores the pending registration in the cache. The name and the already-hashed password go in the cache; nothing is written to the `users` table.
   - Emails a code.
   - Returns a `verification_token`.
2. **`register/verify`** with the token and the code:
   - Creates the user with `email_verified_at` set.
   - Writes `auth.user.registered` to the outbox in the same DB transaction.
   - Returns a Sanctum token.
3. The welcome email goes out when the Notifications consumer processes `auth.user.registered`.

**Why the verification token exists:** only the token from the latest `register` call for an email is accepted. Without it, an attacker could register a victim's email with the attacker's own password. The victim would then receive a code, and if the victim entered it, the account would end up with the attacker's password.

### Forgot password

1. **`forgot-password`:**
   - Returns a 422 for an unknown email (`passwords.user`).
   - Otherwise emails a code and returns a `verification_token`.
2. **`forgot-password/verify`** confirms the code. The same `verification_token` then authorises the reset for 15 minutes.
3. **`reset-password`:**
   - Sets the new password.
   - Marks the email verified.
   - Revokes all of the user's tokens, signing them out on every device.

### Google / Apple sign-in

The mobile app signs in with Google or Apple and sends the resulting **ID token** to `auth/social/{provider}`. The API verifies it against the provider's public keys (JWKS, cached for 1 hour). It checks:
- the signature (RS256)
- `exp` (required)
- the issuer
- the audience (must be one of the configured client IDs)
- `sub`

It then:
1. Signs in the user already linked to that provider account (`social_accounts` table), **or**
2. Links the user with the same email. The provider must report the email as verified. If that user was unverified, the API also:
   - resets their password to a random value
   - marks them verified
   - revokes their tokens

   This locks out anyone who registered that email without owning it. **Or**
3. Creates a new verified user with a random password and publishes `auth.user.registered`, so they also get the welcome email. The user can set a real password through forgot password.

Apple only gives the user's name to the app, not in the token, so the app can send `name` in the body.

### Verification code rules

Defined in `VerificationCodeService`:

| Rule | Value |
|---|---|
| Code length | 4 digits |
| Code valid for | 5 minutes |
| Resend cooldown | 60 s. A call within the cooldown returns `code_sent: false` with `retry_after`. |
| Max codes per email | 4 per 15 minutes, then a 422 |
| Wrong codes before lockout | 4, then locked for 5 minutes |
| **Code outside production** | **Always `5555`**, so testers don't need a real inbox |

Codes and pending registrations are stored in the **cache**, keyed by purpose and email. They are not in the database.

## RabbitMQ / messaging

There's no new infrastructure: Auth writes to the outbox, `rabbitmq:relay-outbox` publishes to the `modular.events` exchange, and `rabbitmq:consume notifications.events.queue` handles the messages.

| Event (routing key) | Published when | Handled by | Sends |
|---|---|---|---|
| `auth.user.registered` (existing) | Registration verified, or a new social user is created | `UserRegisteredHandler` | `WelcomeNotification` |
| `auth.verification_code.issued` (**new**) | A code is sent for registration or password reset | `VerificationCodeIssuedHandler` | `VerificationCodeNotification` |

- **`auth.verification_code.issued` payload:** `email`, `code`, `purpose` (`registration` \| `password_reset`), `expires_in_minutes`.
- **Mail is sent inside the RabbitMQ consumer**, not through Laravel's queue. The notifications don't implement `ShouldQueue`, so a mail failure goes through the existing RabbitMQ retry and dead-letter handling. `queue:work` isn't needed.
- **Notifications stays decoupled from Auth's internals.** It only depends on `App\Modules\Auth\IntegrationEvents`, which is Auth's public contract, and never on Auth's Domain or Application code.

## Files

### Moved from Users to Auth

Moved with `git mv`; namespaces updated.
- `Application/DTOs/AuthenticatedUser.php`
- `Application/DTOs/LoginData.php`
- `Application/DTOs/RegisterUserData.php` (`deviceName` removed)
- `Application/Services/UserAuthService.php` (rewritten for code-based registration)
- `Domain/Exceptions/InvalidCredentialsException.php`
- `Presentation/Http/Controllers/AuthController.php`
- `Presentation/Http/Requests/LoginRequest.php`
- `Presentation/Http/Requests/RegisterRequest.php`
- `Presentation/Http/Resources/AuthenticatedUserResource.php`

### New in `app/modules/Auth`

| File | Purpose |
|---|---|
| `IntegrationEvents/VerificationCodeIssued.php` | New integration event |
| `IntegrationEvents/VerificationPurpose.php` | Enum: `Registration`, `PasswordReset` |
| `Domain/Enums/SocialProvider.php` | Enum: `Google`, `Apple` (also used for route binding) |
| `Domain/Contracts/IdentityTokenVerifier.php` | Interface for verifying ID tokens |
| `Domain/Exceptions/EmailNotVerifiedException.php` | Thrown on login with an unverified email |
| `Domain/Exceptions/InvalidIdentityTokenException.php` | Thrown for a bad Google/Apple token |
| `Application/DTOs/VerifyCodeData.php` | Input for verify endpoints |
| `Application/DTOs/VerificationCodeStatus.php` | Output of send/resend |
| `Application/DTOs/SocialIdentity.php` | Verified identity from a provider |
| `Application/DTOs/SocialLoginData.php` | Input for social sign-in |
| `Application/Services/VerificationCodeService.php` | Code generation, limits and verification |
| `Application/Services/PasswordResetService.php` | Forgot / reset password |
| `Application/Services/SocialAuthService.php` | Google / Apple sign-in and account linking |
| `Infrastructure/Services/OidcIdentityTokenVerifier.php` | JWKS + `firebase/php-jwt` implementation |
| `Infrastructure/Models/SocialAccount.php` | Eloquent model for `social_accounts` |
| `Presentation/Http/Controllers/PasswordResetController.php` | Forgot / reset password endpoints |
| `Presentation/Http/Controllers/SocialLoginController.php` | Social sign-in endpoint |
| `Presentation/Http/Requests/Concerns/NormalizesEmail.php` | Lowercases `email` before validation |
| `Presentation/Http/Requests/{VerifyCode,ResendCode,ForgotPassword,ResetPassword,SocialLogin}Request.php` | Form requests |
| `Presentation/Http/Resources/VerificationCodeResource.php` | Verification status response |

### Modified in Auth

- **`Application/Services/AuthEventPublisher.php`:** added `verificationCodeIssued()`.
- **`Providers/AuthServiceProvider.php`:** binds `IdentityTokenVerifier` to `OidcIdentityTokenVerifier`.
- **`Routes/api.php`:** replaced the `/test` placeholder with the endpoints above.

### Notifications module

| File | Change |
|---|---|
| `Application/Notifications/WelcomeNotification.php` | **New.** Welcome email: greeting by name, "Get started" button |
| `Application/Notifications/VerificationCodeNotification.php` | **New.** Code email. Subject depends on purpose. |
| `Application/Handlers/VerificationCodeIssuedHandler.php` | **New.** Sends the code email |
| `Application/Handlers/UserRegisteredHandler.php` | Now sends the welcome email (previously only logged) |
| `Providers/NotificationsServiceProvider.php` | Subscribes `auth.verification_code.issued` on `notifications.events.queue` |

### Users module

- **`Presentation/Http/Controllers/ProfileController.php`:** new; serves `GET /users/me`.
- **`Routes/api.php`:** only `me` remains.

### Database

- **`database/migrations/2026_10_28_100612_create_social_accounts_table.php`:** `user_id` (FK, cascade on delete), `provider`, `provider_user_id`, unique on (`provider`, `provider_user_id`).
- **`database/factories/Modules/Auth/Infrastructure/Models/SocialAccountFactory.php`:** includes a `google()` state.

### Config and dependencies

- **`composer.json` / `composer.lock`:** added `firebase/php-jwt` ^7.2.
- **`config/services.php`:** added `google.client_ids` and `apple.client_ids`, read from comma-separated env vars.
- **`.env.example`:** added `GOOGLE_CLIENT_IDS=` and `APPLE_CLIENT_IDS=`.

### Tests

- **`tests/TestCase.php`:** added helpers.
  - `deliverOutboxMessages()` stands in for the relay and the broker by dispatching outbox messages to subscribed handlers.
  - `lastVerificationCode()` reads the last code from the outbox.
- **New tests:**
  - `RegistrationTest`
  - `PasswordResetTest`
  - `SocialLoginTest`: uses a generated RSA key and a faked JWKS, and covers wrong audience, wrong issuer, expired, missing `exp`, and forged signature.
  - `VerificationCodeNotificationTest`
  - `WelcomeNotificationTest`
- **`UserAuthTest`:** rewritten for the new login, logout and me routes. Adds cases for unverified login and email case-insensitivity.

## Setup after pulling

1. `composer install`, for `firebase/php-jwt`.
2. `php artisan migrate`, to create `social_accounts`. Also `personal_access_tokens`, if your DB doesn't have it yet.
3. `php artisan rabbitmq:setup`, to bind `auth.verification_code.issued` to `notifications.events.queue`. Without this, code emails are never sent.
4. Set `GOOGLE_CLIENT_IDS` and `APPLE_CLIENT_IDS` in `.env`:
   - Google: the Android, iOS and web OAuth client IDs.
   - Apple: the bundle ID and/or Services ID.

   Social sign-in returns a 500 until these are set; everything else works without them.
5. Run both processes, using PHP 8.3:
   ```bash
   php artisan rabbitmq:relay-outbox
   php artisan rabbitmq:consume notifications.events.queue
   ```
   With `MAIL_MAILER=log`, emails appear in `storage/logs/laravel.log`.

### Production

- **Supervisor** keeps `rabbitmq:relay-outbox` (1 process) and `rabbitmq:consume notifications.events.queue --max-time=3600` running.
- **Cron** is only needed for `php artisan schedule:run`, every minute.
- **On each deploy:**
  - `php artisan migrate --force`
  - `php artisan rabbitmq:setup`
  - `supervisorctl restart auth-modular:*`

## Known issues and follow-ups

- **Folder name case: fix before deploying to Linux.** This was already the case before these changes. The folder is `app/modules` (lowercase), but namespaces are `App\Modules\...` and `ModuleServiceProvider` looks in `app_path('Modules')`.
  - macOS ignores the difference in case, so it works locally.
  - On a case-sensitive Linux filesystem, no module would load: no routes, no subscriptions.
  - Fix: `git mv app/modules app/Modules-tmp && git mv app/Modules-tmp app/Modules`
- **Security advisories:** `composer audit` reports advisories for `league/commonmark` and `symfony/http-foundation`. These were already there and aren't touched by these changes; updating them needs approval.
- **Possible follow-up:** schedule pruning of old `processed_messages` rows.
