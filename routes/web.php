<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Middleware\VerifyAppToken;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyAppToken::class)->group(function () {
    Route::post('/apply/{listing}', [ApplicationController::class, 'store'])->name('apply');
});
