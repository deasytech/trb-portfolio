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