<?php

namespace Tests\Feature;

use App\Modules\Auth\IntegrationEvents\VerificationPurpose;
use App\Modules\Notifications\Application\Notifications\VerificationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerificationCodeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_code_is_emailed_once_the_event_is_consumed(): void
    {
        Notification::fake();

        $this->postJson(route('api.auth.register'), [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertOk();

        Notification::assertNothingSent();

        $this->deliverOutboxMessages();

        $code = $this->lastVerificationCode();

        Notification::assertSentOnDemand(
            VerificationCodeNotification::class,
            fn (VerificationCodeNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notification->code === $code
                && $notification->purpose === VerificationPurpose::Registration
                && $notifiable->routes['mail'] === 'ahmed@example.com',
        );
    }

    public function test_email_subject_depends_on_why_the_code_was_sent(): void
    {
        $registration = (new VerificationCodeNotification('1234', VerificationPurpose::Registration, 5))
            ->toMail(new AnonymousNotifiable);
        $passwordReset = (new VerificationCodeNotification('1234', VerificationPurpose::PasswordReset, 5))
            ->toMail(new AnonymousNotifiable);

        $this->assertSame('Verify your email address', $registration->subject);
        $this->assertSame('Reset your password', $passwordReset->subject);
        $this->assertContains('Your verification code is 1234.', $registration->introLines);
        $this->assertContains('It expires in 5 minutes.', $registration->introLines);
    }
}
