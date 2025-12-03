<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/dashboard', 'admin.dashboard')->name('dashboard');
});
