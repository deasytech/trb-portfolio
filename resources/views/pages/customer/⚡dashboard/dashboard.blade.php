<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card>
            <flux:subheading>{{ __('Available Balance') }}</flux:subheading>
            <x-money-lines :totals="$this->totals" key="available_minor" />
        </flux:card>
        <flux:card>
            <flux:subheading>{{ __('Funds On Hold') }}</flux:subheading>
            <x-money-lines :totals="$this->totals" key="held_minor" />
        </flux:card>
        <flux:card>
            <flux:subheading>{{ __('Total Assets') }}</flux:subheading>
            <x-money-lines :totals="$this->totals" key="assets_minor" />
        </flux:card>
        <flux:card>
            <flux:subheading>{{ __('Pending Transfers') }}</flux:subheading>
            <x-money-lines :totals="$this->totals" key="pending_minor" />
            <flux:text size="sm" class="mt-1">{{ trans_choice(':count request|:count requests', $this->pendingCount) }}</flux:text>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading size="lg">{{ __('Account Summary') }}</flux:heading>
            @if ($this->primaryAccount)
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <dt class="text-zinc-500">{{ __('Account holder') }}</dt>
                    <dd>{{ auth()->user()->name }}</dd>
                    <dt class="text-zinc-500">{{ __('Account number') }}</dt>
                    <dd>{{ $this->primaryAccount->maskedNumber() }}</dd>
                    <dt class="text-zinc-500">{{ __('Type') }}</dt>
                    <dd>{{ __($this->primaryAccount->account_type->label()) }}</dd>
                    <dt class="text-zinc-500">{{ __('Currency') }}</dt>
                    <dd>{{ $this->primaryAccount->currency }}</dd>
                    <dt class="text-zinc-500">{{ __('Status') }}</dt>
                    <dd><x-status-badge :status="$this->primaryAccount->status" /></dd>
                </dl>
                <flux:link :href="route('customer.account-summary')" wire:navigate class="mt-4 inline-block text-sm">{{ __('View all accounts') }}</flux:link>
            @else
                <flux:text class="mt-2">{{ __('You do not have an account yet. Please contact support to get started.') }}</flux:text>
            @endif
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Recent Transfers') }}</flux:heading>
                <flux:button size="sm" variant="primary" :href="route('customer.transfers.index')" wire:navigate>{{ __('Transfers') }}</flux:button>
            </div>
            @forelse ($this->recentTransfers as $transfer)
                <a href="{{ route('customer.transfers.show', $transfer) }}" wire:navigate wire:key="transfer-{{ $transfer->id }}" class="mt-3 flex items-center justify-between gap-3 rounded-md p-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                    <span>
                        <span class="block font-medium">{{ $transfer->reference }}</span>
                        <span class="text-zinc-500">{{ $transfer->created_at->timezone(auth()->user()->displayTimezone())->format('M j, Y') }}</span>
                    </span>
                    <span class="flex items-center gap-3">
                        <span class="tabular-nums">{{ \App\Services\Money::format($transfer->amount_minor, $transfer->currency) }}</span>
                        <x-status-badge :status="$transfer->status" />
                    </span>
                </a>
            @empty
                <flux:text class="mt-2">{{ __('No transfers yet.') }}</flux:text>
            @endforelse
            <flux:link :href="route('customer.history')" wire:navigate class="mt-4 inline-block text-sm">{{ __('View full history') }}</flux:link>
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="lg">{{ __('Funds On Hold') }}</flux:heading>
        @forelse ($this->activeHolds as $hold)
            <div wire:key="hold-{{ $hold->id }}" class="mt-3 flex items-center justify-between text-sm">
                <span>{{ $hold->reason }}</span>
                <span class="tabular-nums">{{ \App\Services\Money::format($hold->remaining_amount_minor, $hold->currency) }}</span>
            </div>
        @empty
            <flux:text class="mt-2">{{ __('No funds are currently on hold.') }}</flux:text>
        @endforelse
        <flux:link :href="route('customer.holds')" wire:navigate class="mt-4 inline-block text-sm">{{ __('View all holds') }}</flux:link>
    </flux:card>
</div>
