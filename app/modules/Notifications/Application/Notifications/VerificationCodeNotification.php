<?php

namespace App\Modules\Notifications\Application\Notifications;

use App\Modules\Auth\IntegrationEvents\VerificationPurpose;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a verification code for sign-up or password reset.
 *
 * Not ShouldQueue for the same reason as WelcomeNotification: it is
 * already sent from the RabbitMQ consumer.
 */
final class VerificationCodeNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly VerificationPurpose $purpose,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(match ($this->purpose) {
                VerificationPurpose::Registration => __('Verify your email address'),
                VerificationPurpose::PasswordReset => __('Reset your password'),
            })
            ->line(__('Your verification code is :code.', ['code' => $this->code]))
            ->line(__('It expires in :minutes minutes.', ['minutes' => $this->expiresInMinutes]))
            ->line(__("If you didn't request it, you can ignore this email."));
    }
}
