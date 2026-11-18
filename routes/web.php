<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ChatController;

Route::get('/', [PortfolioController::class, 'index'])->name('home');
Route::post('/chat/message', [ChatController::class, 'sendMessage'])->middleware('throttle:20,1')->name('chat.message');

// Breeze routes removed in favor of Filament-only administration
