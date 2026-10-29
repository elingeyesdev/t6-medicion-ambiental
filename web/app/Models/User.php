<?php

namespace App\Models;

use App\Enums\RolCuenta;
use App\Notifications\RecuperarAcceso;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = ['rol' => 'visualizador', 'activo' => true, 'sesion_version' => 1];

    public function preferencias(): HasOne
    {
        return $this->hasOne(PreferenciasCuenta::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RecuperarAcceso($token));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'sesion_version',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => RolCuenta::class,
            'activo' => 'boolean',
            'sesion_version' => 'integer',
        ];
    }
}
