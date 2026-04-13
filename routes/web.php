<?php

use App\Http\Controllers\ApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/apply/{listing}', [ApplicationController::class, 'store'])->name('apply');
});
