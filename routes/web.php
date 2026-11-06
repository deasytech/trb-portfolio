<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'customer', 'verified'])
    ->name('customer.')
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        // Placeholders until the Phase 3 portal pages are implemented.
        Route::view('account-summary', 'customer.coming-soon', ['title' => 'Account Summary'])->name('account-summary');
        Route::view('transfers', 'customer.coming-soon', ['title' => 'Transfers'])->name('transfers.index');
        Route::view('history', 'customer.coming-soon', ['title' => 'History'])->name('history');
        Route::view('funds-on-hold', 'customer.coming-soon', ['title' => 'Funds On Hold'])->name('holds');
    });

require __DIR__.'/settings.php';
