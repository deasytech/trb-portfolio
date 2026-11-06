<div class="space-y-6">
    <flux:card>
        <flux:subheading>{{ __('Total active amount on hold') }}</flux:subheading>
        <x-money-lines :totals="$this->totals" key="held_minor" />
        <flux:text size="sm" class="mt-1">{{ __('Holds are placed and released by our team. You can view them here.') }}</flux:text>
    </flux:card>

    <flux:card>
        @if ($this->holds->isEmpty())
            <flux:heading size="lg">{{ __('No holds') }}</flux:heading>
            <flux:text class="mt-2">{{ __('You have no funds on hold.') }}</flux:text>
        @else
            <flux:table :paginate="$this->holds">
                <flux:table.columns>
                    <flux:table.column>{{ __('Reason') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Placed') }}</flux:table.column>
                    <flux:table.column>{{ __('Expected release') }}</flux:table.column>
                    <flux:table.column>{{ __('Related transfer') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->holds as $hold)
                        <flux:table.row wire:key="hold-{{ $hold->id }}">
                            <flux:table.cell>{{ $hold->reason }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ \App\Services\Money::format($hold->amount_minor, $hold->currency) }}</flux:table.cell>
                            <flux:table.cell>{{ $hold->created_at->timezone(auth()->user()->displayTimezone())->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $hold->expires_at?->timezone(auth()->user()->displayTimezone())->format('M j, Y') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $hold->relatedTransfer?->reference ?? '—' }}</flux:table.cell>
                            <flux:table.cell><x-status-badge :status="$hold->status" /></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>