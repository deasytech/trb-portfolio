<?php

use App\Enums\TransferStatus;
use App\Models\Transfer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

return new #[Title('Transfer Details')] class extends Component {
    #[Locked]
    public Transfer $transfer;

    public function mount(Transfer $transfer): void
    {
        $this->authorize('view', $transfer);

        $this->transfer = $transfer->load('sourceAccount');
    }

    /**
     * @return array<int, array{label: string, at: \Illuminate\Support\Carbon|null}>
     */
    #[Computed]
    public function timeline(): array
    {
        $t = $this->transfer;

        $steps = [['label' => 'Submitted', 'at' => $t->submitted_at ?? $t->created_at]];

        if ($t->approved_at) {
            $steps[] = ['label' => 'Approved', 'at' => $t->approved_at];
        }
        if ($t->processed_at) {
            $steps[] = ['label' => 'Processing', 'at' => $t->processed_at];
        }
        if ($t->completed_at) {
            $steps[] = ['label' => 'Completed', 'at' => $t->completed_at];
        }
        if ($t->rejected_at) {
            $steps[] = ['label' => 'Rejected', 'at' => $t->rejected_at];
        }
        if (in_array($t->status, [TransferStatus::Cancelled, TransferStatus::Failed], true)) {
            $steps[] = ['label' => $t->status->label(), 'at' => $t->updated_at];
        }

        return $steps;
    }
}; ?>

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
