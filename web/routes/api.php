<?php

use App\Http\Controllers\SaludController;
use Illuminate\Support\Facades\Route;

// Comprobación operativa, independiente del contrato ambiental de #18.
Route::get('/salud', SaludController::class)->name('api.salud');
