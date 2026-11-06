<?php

test('the home page redirects to the login page', function () {
    $this->get(route('home'))->assertRedirect('/login');
});

test('the login page uses the split layout', function () {
    $this->get(route('login'))->assertOk()->assertSee('Welcome back')->assertSee('Your portfolio, always in view.');
});
