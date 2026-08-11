<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly WorkspaceInvitation $invitation,
        public readonly string $plainToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Convite para o workspace '.$this->invitation->workspace->name)
            ->greeting('Você recebeu um convite para o Waflow')
            ->line('Workspace: '.$this->invitation->workspace->name)
            ->line('Perfil de acesso: '.$this->invitation->role)
            ->action('Aceitar convite', route('invitations.accept', $this->plainToken))
            ->line('O convite expira em 7 dias.');
    }
}
