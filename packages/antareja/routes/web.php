<?php

use Antareja\Http\Controllers\AboutController;
use Antareja\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('antareja')
    ->name('antareja.')
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('home');
        Route::get('about', AboutController::class)->name('about');
    });
