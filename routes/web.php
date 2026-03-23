<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\FeedController;
use App\Http\Middleware\VerifyAppToken;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyAppToken::class)->group(function () {
    Route::get('/feed.xml', FeedController::class)->name('feed');
    Route::post('/apply/{listing}', [ApplicationController::class, 'store'])->name('apply');
});
