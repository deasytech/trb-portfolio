<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-trb-canvas dark:bg-zinc-900">
        <flux:sidebar sticky collapsible="mobile" class="dark border-e-0 bg-trb-navy! text-white">
            <flux:sidebar.header>
                <a href="{{ route('customer.dashboard') }}" wire:navigate class="flex items-center gap-2 text-sm font-bold tracking-wide text-white">
                    <span aria-hidden="true">🏦</span>
                    <span>{{ __('TRB PORTFOLIO') }}</span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav aria-label="{{ __('Main navigation') }}">
                <flux:sidebar.item icon="home" :href="route('customer.dashboard')" :current="request()->routeIs('customer.dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="wallet" :href="route('customer.account-summary')" :current="request()->routeIs('customer.account-summary')" wire:navigate>
                    {{ __('Account Summary') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="arrows-right-left" :href="route('customer.transfers.index')" :current="request()->routeIs('customer.transfers.*')" wire:navigate>
                    {{ __('Transfers') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="clock" :href="route('customer.history')" :current="request()->routeIs('customer.history')" wire:navigate>
                    {{ __('History') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="lock-closed" :href="route('customer.holds')" :current="request()->routeIs('customer.holds')" wire:navigate>
                    {{ __('Funds On Hold') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="user" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>
                    {{ __('Profile') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit', 'security.edit')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        <flux:header class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" :aria-label="__('Open navigation')" />

            <flux:heading level="1" size="xl" class="ms-2">{{ $title ?? config('app.name') }}</flux:heading>

            <flux:spacer />

            <span class="me-4 hidden text-sm text-zinc-600 sm:inline dark:text-zinc-300">{{ auth()->user()->name }}</span>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" data-test="logout-button" class="cursor-pointer rounded-md bg-trb-danger px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-2 focus:outline-offset-2 focus:outline-trb-danger">
                    {{ __('Logout') }}
                </button>
            </form>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
