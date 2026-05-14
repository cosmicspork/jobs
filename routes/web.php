<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\DigestUnsubscribeController;
use App\Http\Controllers\UserDataExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/apply/{listing}', [ApplicationController::class, 'store'])->name('apply');

    Route::get('/account/data-export/{user}/{file}', [UserDataExportController::class, 'download'])
        ->middleware('signed')
        ->where('file', '[A-Za-z0-9_\-.]+\.zip')
        ->name('user-data.download');
});

Route::get('/digest/unsubscribe/{user}', DigestUnsubscribeController::class)
    ->name('digest.unsubscribe');
