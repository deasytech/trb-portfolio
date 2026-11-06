<?php

use App\Enums\Theme;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

return new #[Title('Settings')] class extends Component {
    public string $theme = 'system';
    public string $timezone = 'UTC';
    public bool $emailNotifications = true;
    public bool $inAppNotifications = true;

    public function mount(): void
    {
        $preference = Auth::user()->preference()->firstOrCreate()->refresh();

        $this->theme = $preference->theme->value;
        $this->timezone = $preference->timezone;
        $this->emailNotifications = $preference->notification_preferences['email'] ?? true;
        $this->inAppNotifications = $preference->notification_preferences['in_app'] ?? true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'theme' => ['required', Rule::enum(Theme::class)],
            'timezone' => ['required', 'timezone:all'],
            'emailNotifications' => ['boolean'],
            'inAppNotifications' => ['boolean'],
        ]);

        Auth::user()->preference()->updateOrCreate([], [
            'theme' => $validated['theme'],
            'timezone' => $validated['timezone'],
            'notification_preferences' => [
                'email' => $validated['emailNotifications'],
                'in_app' => $validated['inAppNotifications'],
            ],
        ]);

        $this->dispatch('theme-saved', theme: $validated['theme']);

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }
}; ?>

<section class="w-full" x-on:theme-saved.window="$flux.appearance = $event.detail.theme">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Settings')" :subheading="__('Theme, timezone and notification preferences')">
        <form wire:submit="save" class="space-y-6">
            <flux:radio.group wire:model="theme" :label="__('Theme')" variant="segmented">
                <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
            </flux:radio.group>

            <flux:select wire:model="timezone" :label="__('Timezone')" searchable>
                @foreach (timezone_identifiers_list() as $zone)
                    <flux:select.option :value="$zone">{{ $zone }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:fieldset>
                <flux:legend>{{ __('Notifications') }}</flux:legend>
                <div class="space-y-3">
                    <flux:switch wire:model="emailNotifications" :label="__('Email notifications')" />
                    <flux:switch wire:model="inAppNotifications" :label="__('In-app notifications')" />
                </div>
            </flux:fieldset>

            <flux:button variant="primary" type="submit" data-test="save-settings-button">{{ __('Save') }}</flux:button>
        </form>

        <flux:separator class="my-6" />
        <flux:text>
            {{ __('Change your password or manage two-factor authentication in') }}
            <flux:link :href="route('security.edit')" wire:navigate>{{ __('Security settings') }}</flux:link>.
        </flux:text>
    </x-pages::settings.layout>
</section>
