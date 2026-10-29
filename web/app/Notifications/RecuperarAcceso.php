<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class RecuperarAcceso extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $enlace = rtrim(config('app.url'), '/').route('password.reset', ['token' => $this->token], false).'?'.http_build_query(['email' => $notifiable->getEmailForPasswordReset()]);
        $mensaje = (new MailMessage)->subject('Recupera tu acceso a GeoSur')->greeting('Hola,')
            ->line('Recibimos una solicitud para cambiar la contraseña de tu cuenta.')
            ->action('Crear una nueva contraseña', $enlace)
            ->line('El enlace vence en 15 minutos. Si no lo pediste, puedes ignorar este mensaje.')
            ->salutation('GeoSur');
        if (app()->environment('local', 'testing')) {
            $mensaje->mailer('log');
        }

        return $mensaje;
    }
}
