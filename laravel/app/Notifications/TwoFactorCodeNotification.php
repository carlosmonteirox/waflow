<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu código de acesso Waflow')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Use o código abaixo para concluir seu acesso ao Waflow:')
            ->line($this->code)
            ->line('O código expira em 5 minutos e pode ser usado apenas uma vez.')
            ->line('Se você não tentou entrar, altere sua senha imediatamente.');
    }
}
