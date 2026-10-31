<?php

namespace App\Notifications;

use App\Notifications\Canales\RecuperacionCorreoLocal;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class RecuperarAcceso extends ResetPassword
{
    public function via($notifiable): array
    {
        return $this->esLocal() ? [RecuperacionCorreoLocal::class] : ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $enlace = rtrim(config('app.url'), '/').route('password.reset', ['token' => $this->token], false).'?'.http_build_query(['email' => $notifiable->getEmailForPasswordReset()]);
        $mensaje = (new MailMessage)->subject('Recupera tu acceso a GeoSur')->greeting('Hola,')
            ->line('Recibimos una solicitud para cambiar la contraseña de tu cuenta.')
            ->action('Crear una nueva contraseña', $enlace)
            ->line('El enlace vence en 15 minutos. Si no lo pediste, puedes ignorar este mensaje.')
            ->salutation('GeoSur');
        if ($this->esLocal()) {
            $mensaje->mailer('log');
        }

        return $mensaje;
    }

    private function esLocal(): bool
    {
        return app()->environment('local', 'testing') || config('mail.default') === 'log';
    }
}
