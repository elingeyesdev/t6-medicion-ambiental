<?php

namespace App\Notifications\Canales;

use App\Services\Notificaciones\CorreoLocal;

final class RecuperacionCorreoLocal
{
    public function send($notifiable, $notification): void
    {
        $mensaje = $notification->toMail($notifiable);
        CorreoLocal::mailer()->html((string) $mensaje->render(), fn ($m) => $m->to($notifiable->getEmailForPasswordReset())->subject($mensaje->subject));
    }
}
