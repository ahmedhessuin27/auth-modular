<?php

namespace App\Modules\Notifications\Application\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Greets a user right after they sign up.
 *
 * Deliberately not ShouldQueue: it is already sent from the RabbitMQ
 * consumer, so a mail failure should throw back into that consumer's
 * retry / dead-letter path rather than land on a second queue.
 */
final class WelcomeNotification extends Notification
{
    public function __construct(
        public readonly string $name,
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
        $appName = config('app.name');

        return (new MailMessage)
            ->subject(__('Welcome to :app', ['app' => $appName]))
            ->greeting(__('Hello :name!', ['name' => $this->name]))
            ->line(__('Thanks for signing up. Your :app account is ready to use.', ['app' => $appName]))
            ->action(__('Get started'), url('/'));
    }
}
