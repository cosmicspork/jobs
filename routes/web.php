<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\DigestUnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/apply/{listing}', [ApplicationController::class, 'store'])->name('apply');
});

Route::get('/digest/unsubscribe/{user}', DigestUnsubscribeController::class)
    ->name('digest.unsubscribe');
