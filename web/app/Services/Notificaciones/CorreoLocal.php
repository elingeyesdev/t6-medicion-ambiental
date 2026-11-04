<?php

namespace App\Services\Notificaciones;

use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\LogTransport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class CorreoLocal
{
    public static function mailer(): Mailer
    {
        $mailer = clone Mail::mailer('log');
        // El log habitual puede filtrar debug. Este registro siempre conserva el correo local.
        $mailer->setSymfonyTransport(new LogTransport(Log::build([
            'driver' => 'single', 'path' => storage_path('logs/correo-local.log'), 'level' => 'debug',
        ])));

        return $mailer;
    }
}
