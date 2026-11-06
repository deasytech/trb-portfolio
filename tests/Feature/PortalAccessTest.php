<?php

use App\Models\User;

dataset('customer routes', [
    'customer.dashboard',
    'customer.account-summary',
    'customer.transfers.index',
    'customer.history',
    'customer.holds',
]);

test('guests are redirected to login from customer pages', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with('customer routes');

test('customers can open every customer page', function (string $route) {
    $this->actingAs(User::factory()->customer()->create())
        ->get(route($route))
        ->assertOk();
})->with('customer routes');

test('the sidebar shows every navigation item', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->get(route('customer.dashboard'))
        ->assertSeeInOrder(['Dashboard', 'Account Summary', 'Transfers', 'History', 'Funds On Hold', 'Profile', 'Settings', 'Logout']);
});

test('staff are sent to the admin panel instead of the customer portal', function () {
    $this->actingAs(User::factory()->administrator()->create())
        ->get(route('customer.dashboard'))
        ->assertRedirect('/admin');
});

test('suspended users are signed out of the portal', function () {
    $this->actingAs(User::factory()->customer()->suspended()->create())
        ->get(route('customer.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('suspended users cannot log in', function () {
    $user = User::factory()->suspended()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

test('logging in records the last login time and a customer reference', function () {
    $user = User::factory()->customer()->create();

    expect($user->customer_reference)->toStartWith('CUS-')->and($user->last_login_at)->toBeNull();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('customer.dashboard', absolute: false));

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('logout invalidates the session', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

test('customers cannot set role or status through mass assignment', function () {
    $user = User::factory()->create();

    $user->fill(['status' => 'suspended', 'customer_reference' => 'HACK', 'name' => 'New Name']);

    expect($user->name)->toBe('New Name')
        ->and($user->isActive())->toBeTrue()
        ->and($user->customer_reference)->not->toBe('HACK');
});
