<div class="max-w-3xl space-y-6">
    <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('customer.transfers.index')" wire:navigate>{{ __('Back to transfers') }}</flux:button>

    <flux:card>
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ $transfer->reference }}</flux:heading>
            <x-status-badge :status="$transfer->status" />
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <dt class="text-zinc-500">{{ __('Created') }}</dt>
            <dd>{{ $transfer->created_at->timezone(auth()->user()->displayTimezone())->format('M j, Y g:i A') }}</dd>
            <dt class="text-zinc-500">{{ __('Type') }}</dt>
            <dd>{{ __($transfer->type->label()) }}</dd>
            <dt class="text-zinc-500">{{ __('Source account') }}</dt>
            <dd>{{ $transfer->sourceAccount?->maskedNumber() ?? '—' }}</dd>
            <dt class="text-zinc-500">{{ __('Recipient') }}</dt>
            <dd>{{ $transfer->beneficiary_name ?? '—' }}</dd>
            <dt class="text-zinc-500">{{ __('Amount') }}</dt>
            <dd class="tabular-nums">{{ \App\Services\Money::format($transfer->amount_minor, $transfer->currency) }}</dd>
            <dt class="text-zinc-500">{{ __('Description') }}</dt>
            <dd>{{ $transfer->description ?? '—' }}</dd>
            @if ($transfer->rejection_reason)
                <dt class="text-zinc-500">{{ __('Reason') }}</dt>
                <dd>{{ $transfer->rejection_reason }}</dd>
            @endif
        </dl>

        @error('cancel') <flux:text class="mt-3 text-red-600">{{ $message }}</flux:text> @enderror
        @if ($transfer->status === \App\Enums\TransferStatus::Pending)
            <flux:button class="mt-4" variant="danger" wire:click="cancel" wire:confirm="{{ __('Cancel this transfer request?') }}">{{ __('Cancel request') }}</flux:button>
        @endif
    </flux:card>

    <flux:card>
        <flux:heading size="lg">{{ __('Status timeline') }}</flux:heading>
        <ol class="mt-4 space-y-3 border-s border-zinc-200 ps-4 dark:border-zinc-700">
            @foreach ($this->timeline as $step)
                <li wire:key="step-{{ $loop->index }}" class="text-sm">
                    <span class="font-medium">{{ __($step['label']) }}</span>
                    <span class="block text-zinc-500">{{ $step['at']?->timezone(auth()->user()->displayTimezone())->format('M j, Y g:i A') }}</span>
                </li>
            @endforeach
        </ol>
    </flux:card>
</div>