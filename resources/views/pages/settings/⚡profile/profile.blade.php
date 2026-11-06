<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your contact details')">
        <dl class="grid grid-cols-2 gap-2 text-sm">
            <dt class="text-zinc-500">{{ __('Customer ID') }}</dt>
            <dd>{{ auth()->user()->customer_reference }}</dd>
            <dt class="text-zinc-500">{{ __('Account status') }}</dt>
            <dd>{{ __(auth()->user()->status->label()) }}</dd>
            <dt class="text-zinc-500">{{ __('Joined') }}</dt>
            <dd>{{ auth()->user()->created_at?->timezone(auth()->user()->displayTimezone())->format('M j, Y') }}</dd>
        </dl>

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <flux:input wire:model="phone" :label="__('Phone number')" type="tel" autocomplete="tel" />
            <flux:input wire:model="country" :label="__('Country (2-letter code)')" type="text" maxlength="2" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
