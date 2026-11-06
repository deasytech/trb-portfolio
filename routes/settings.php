<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'customer'])->group(function () {
    Route::livewire('profile', 'pages::settings.profile')->name('customer.profile');
});

Route::middleware(['auth', 'customer', 'verified'])->group(function () {
    Route::livewire('settings', 'pages::settings.appearance')->name('customer.settings');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
