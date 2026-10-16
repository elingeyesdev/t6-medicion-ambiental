<?php

use App\Http\Controllers\InicioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InicioController::class, 'index'])->name('inicio');
Route::get('/modulos/{modulo}', [InicioController::class, 'modulo'])->name('modulos.mostrar');
