<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One-time code mail for the mobile app (type: reset | verify). */
class AuthCodeNotification extends Notification
{
    public function __construct(private string $type, private string $code, private int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('store.api.mail_'.$this->type.'_subject', ['store' => current_tenant()?->name]))
            ->line(__('store.api.mail_'.$this->type.'_line'))->line($this->code)->line(__('store.api.mail_expires', ['minutes' => $this->minutes]));
    }
}
