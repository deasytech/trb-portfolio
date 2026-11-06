<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-white antialiased dark:bg-zinc-900">
        <div class="grid min-h-dvh lg:grid-cols-2">
            <aside class="relative hidden flex-col justify-between overflow-hidden bg-trb-navy p-12 text-white lg:flex">
                <div aria-hidden="true" class="pointer-events-none absolute -end-32 -top-32 size-96 rounded-full bg-trb-blue/30 blur-3xl"></div>
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -start-24 size-96 rounded-full bg-trb-blue/20 blur-3xl"></div>

                <div class="relative flex items-center gap-2 text-lg font-bold tracking-wide">
                    <span aria-hidden="true">🏦</span>
                    <span>{{ __('TRB PORTFOLIO') }}</span>
                </div>

                <div class="relative max-w-md space-y-8">
                    <div class="space-y-3">
                        <h2 class="text-4xl font-semibold leading-tight">{{ __('Your portfolio, always in view.') }}</h2>
                        <p class="text-base text-slate-300">{{ __('Check balances, follow transfers and review funds on hold, securely, anywhere.') }}</p>
                    </div>

                    <ul class="space-y-4 text-sm text-slate-200">
                        @foreach ([
                            ['shield-check', 'Bank-grade security with optional two-factor sign-in'],
                            ['chart-bar', 'Live balances calculated from your ledger'],
                            ['arrows-right-left', 'Transparent transfer tracking from request to completion'],
                        ] as [$icon, $text])
                            <li class="flex items-center gap-3">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10">
                                    <flux:icon :name="$icon" class="size-4" />
                                </span>
                                {{ __($text) }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="relative text-xs text-slate-400">&copy; {{ date('Y') }} {{ __('TRB Portfolio') }}</p>
            </aside>

            <main class="flex items-center justify-center px-6 py-12 sm:px-10">
                <div class="w-full max-w-sm space-y-8">
                    <div class="flex items-center justify-center gap-2 text-lg font-bold tracking-wide text-trb-navy lg:hidden dark:text-white">
                        <span aria-hidden="true">🏦</span>
                        <span>{{ __('TRB PORTFOLIO') }}</span>
                    </div>
                    {{ $slot }}
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
