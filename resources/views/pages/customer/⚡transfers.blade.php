<?php

use App\Enums\TransferStatus;
use App\Models\Transfer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

return new #[Title('Transfers')] class extends Component {
    use WithPagination;

    /**
     * Transfer counts per status group for the overview cards.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function overview(): array
    {
        $counts = Transfer::query()->where('customer_id', Auth::id())
            ->selectRaw('status, COUNT(*) AS aggregate_count')->groupBy('status')
            ->pluck('aggregate_count', 'status');

        $count = fn (TransferStatus ...$statuses): int => (int) collect($statuses)->sum(fn ($s) => $counts[$s->value] ?? 0);

        return [
            'Pending' => $count(...TransferStatus::open()),
            'Completed' => $count(TransferStatus::Completed),
            'Rejected' => $count(TransferStatus::Rejected, TransferStatus::Failed),
            'Cancelled' => $count(TransferStatus::Cancelled),
        ];
    }

    #[Computed]
    public function transfers()
    {
        return Transfer::query()->where('customer_id', Auth::id())->latest('id')->paginate(10);
    }
}; ?>

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->overview as $label => $count)
            <flux:card wire:key="overview-{{ $label }}">
                <flux:subheading>{{ __($label) }}</flux:subheading>
                <div class="text-2xl font-semibold tabular-nums">{{ $count }}</div>
            </flux:card>
        @endforeach
    </div>

    <flux:card>
        <flux:heading size="lg">{{ __('Transfer Requests') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Transfers are reviewed before they are posted to your account.') }}</flux:text>

        @if ($this->transfers->isEmpty())
            <flux:text class="mt-6">{{ __('You have not made any transfer requests yet.') }}</flux:text>
        @else
            <flux:table :paginate="$this->transfers" class="mt-4">
                <flux:table.columns>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->transfers as $transfer)
                        <flux:table.row wire:key="transfer-{{ $transfer->id }}">
                            <flux:table.cell>
                                <flux:link :href="route('customer.transfers.show', $transfer)" wire:navigate>{{ $transfer->reference }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $transfer->created_at->timezone(auth()->user()->displayTimezone())->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell>{{ __($transfer->type->label()) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ \App\Services\Money::format($transfer->amount_minor, $transfer->currency) }}</flux:table.cell>
                            <flux:table.cell><x-status-badge :status="$transfer->status" /></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
