<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreferenciasCuenta extends Model
{
    protected $table = 'preferencias_cuenta';

    protected $fillable = ['latitud', 'longitud', 'notif_email', 'notif_whatsapp', 'whatsapp_destino', 'notif_telegram', 'telegram_destino'];

    protected function casts(): array
    {
        return ['latitud' => 'float', 'longitud' => 'float', 'notif_email' => 'boolean', 'notif_whatsapp' => 'boolean', 'notif_telegram' => 'boolean'];
    }

    public static function vacias(): array
    {
        return ['latitud' => null, 'longitud' => null, 'notif_email' => false, 'notif_whatsapp' => false, 'whatsapp_destino' => null, 'notif_telegram' => false, 'telegram_destino' => null];
    }
}
