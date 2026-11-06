<?php

use App\Services\AccountBalanceCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

return new #[Title('Account Summary')] class extends Component {
    /**
     * @return array<int, array{account: \App\Models\Account, balance: \App\Services\AccountBalance}>
     */
    #[Computed]
    public function accounts(): array
    {
        $calculator = app(AccountBalanceCalculator::class);

        return Auth::user()->accounts()->orderBy('id')->get()
            ->map(fn ($account) => ['account' => $account, 'balance' => $calculator->forAccount($account)])
            ->all();
    }
}; ?>

<div class="grid gap-6 lg:grid-cols-2">
    @forelse ($this->accounts as $row)
        @php($account = $row['account'])
        @php($balance = $row['balance'])
        <flux:card wire:key="account-{{ $account->id }}">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __($account->account_type->label()) }} {{ __('Account') }}</flux:heading>
                <x-status-badge :status="$account->status" />
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-zinc-500">{{ __('Account holder') }}</dt>
                <dd>{{ auth()->user()->name }}</dd>
                <dt class="text-zinc-500">{{ __('Account number') }}</dt>
                <dd>{{ $account->maskedNumber() }}</dd>
                <dt class="text-zinc-500">{{ __('Currency') }}</dt>
                <dd>{{ $account->currency }}</dd>
                <dt class="text-zinc-500">{{ __('Ledger balance') }}</dt>
                <dd class="tabular-nums">{{ \App\Services\Money::format($balance->ledgerMinor, $account->currency) }}</dd>
                <dt class="text-zinc-500">{{ __('Available balance') }}</dt>
                <dd class="tabular-nums">{{ \App\Services\Money::format($balance->availableMinor(), $account->currency) }}</dd>
                <dt class="text-zinc-500">{{ __('Funds on hold') }}</dt>
                <dd class="tabular-nums">{{ \App\Services\Money::format($balance->heldMinor, $account->currency) }}</dd>
                @if ($account->opened_at)
                    <dt class="text-zinc-500">{{ __('Opened') }}</dt>
                    <dd>{{ $account->opened_at->timezone(auth()->user()->displayTimezone())->format('M j, Y') }}</dd>
                @endif
            </dl>
        </flux:card>
    @empty
        <flux:card class="lg:col-span-2">
            <flux:heading size="lg">{{ __('No accounts yet') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Your accounts will appear here once they are opened for you.') }}</flux:text>
        </flux:card>
    @endforelse
</div>
