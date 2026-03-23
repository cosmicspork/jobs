<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

Route::get('/feed.xml', FeedController::class)->name('feed');
Route::post('/apply/{listing}', [ApplicationController::class, 'store'])->name('apply');
