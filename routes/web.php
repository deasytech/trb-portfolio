<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'customer', 'verified'])
    ->name('customer.')
    ->group(function () {
        Route::livewire('dashboard', 'pages::customer.dashboard')->name('dashboard');
        Route::livewire('account-summary', 'pages::customer.account-summary')->name('account-summary');
        Route::livewire('transfers', 'pages::customer.transfers')->name('transfers.index');
        Route::livewire('transfers/{transfer}', 'pages::customer.transfer-details')->name('transfers.show');
        Route::livewire('history', 'pages::customer.history')->name('history');
        Route::livewire('funds-on-hold', 'pages::customer.holds')->name('holds');
    });

require __DIR__.'/settings.php';
