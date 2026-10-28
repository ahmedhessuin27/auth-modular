<?php

namespace Tests\Feature;

use App\Modules\Notifications\Application\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WelcomeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_is_sent_a_welcome_notification_once_the_event_is_consumed(): void
    {
        Notification::fake();

        $verificationToken = $this->postJson(route('api.auth.register'), [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->json('data.verification_token');

        $this->postJson(route('api.auth.register.verify'), [
            'email' => 'ahmed@example.com',
            'verification_token' => $verificationToken,
            'code' => $this->lastVerificationCode(),
        ])->assertCreated();

        // Signing up only writes events to the outbox; nothing is sent
        // during the HTTP requests.
        Notification::assertNothingSent();

        $this->deliverOutboxMessages();

        Notification::assertSentOnDemand(
            WelcomeNotification::class,
            fn (WelcomeNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notification->name === 'Ahmed'
                && $notifiable->routes['mail'] === ['ahmed@example.com' => 'Ahmed'],
        );
    }

    public function test_welcome_email_greets_the_user_by_name(): void
    {
        $mail = (new WelcomeNotification('Ahmed'))->toMail(new AnonymousNotifiable);

        $this->assertSame('Welcome to '.config('app.name'), $mail->subject);
        $this->assertSame('Hello Ahmed!', $mail->greeting);
        $this->assertSame(url('/'), $mail->actionUrl);
    }
}
