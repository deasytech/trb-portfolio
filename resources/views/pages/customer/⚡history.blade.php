<?php

use App\Enums\LedgerTransactionType;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('History')] class extends Component {
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $type = '';
    #[Url] public string $accountId = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';
    #[Url] public string $minAmount = '';
    #[Url] public string $maxAmount = '';

    public ?int $selectedId = null;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:'.implode(',', array_column(LedgerTransactionType::cases(), 'value'))],
            'accountId' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'minAmount' => ['nullable', 'numeric', 'min:0'],
            'maxAmount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function updated(string $property): void
    {
        if ($property !== 'selectedId') {
            $this->validateOnly($property);
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'type', 'accountId', 'from', 'to', 'minAmount', 'maxAmount');
        $this->resetValidation();
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $this->selectedId = $this->baseQuery()->whereKey($id)->exists() ? $id : null;
    }

    #[Computed]
    public function accounts()
    {
        return Auth::user()->accounts()->orderBy('id')->get();
    }

    #[Computed]
    public function selected(): ?LedgerTransaction
    {
        return $this->selectedId ? $this->baseQuery()->with('account')->find($this->selectedId) : null;
    }

    #[Computed]
    public function transactions()
    {
        $query = $this->baseQuery()->with('account')->latest('posted_at')->latest('id');

        if ($this->getErrorBag()->isNotEmpty()) {
            return $query->whereRaw('1 = 0')->paginate(15);
        }

        return $query
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('reference', 'like', '%'.$this->search.'%')
                ->orWhere('description', 'like', '%'.$this->search.'%')))
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when($this->accountId !== '', fn ($q) => $q->where('account_id', (int) $this->accountId))
            ->when($this->from !== '', fn ($q) => $q->where('posted_at', '>=', \Illuminate\Support\Carbon::parse($this->from, Auth::user()->displayTimezone())->startOfDay()))
            ->when($this->to !== '', fn ($q) => $q->where('posted_at', '<=', \Illuminate\Support\Carbon::parse($this->to, Auth::user()->displayTimezone())->endOfDay()))
            ->when($this->minAmount !== '', fn ($q) => $q->where('amount_minor', '>=', (int) round($this->minAmount * 100)))
            ->when($this->maxAmount !== '', fn ($q) => $q->where('amount_minor', '<=', (int) round($this->maxAmount * 100)))
            ->paginate(15);
    }

    protected function baseQuery()
    {
        return LedgerTransaction::query()->whereIn('account_id', Auth::user()->accounts()->select('id'));
    }
}; ?>

<div class="space-y-6">
    <flux:card>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input wire:model.live.debounce.400ms="search" :label="__('Reference or description')" type="search" />
            <flux:select wire:model.live="type" :label="__('Type')">
                <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                @foreach (\App\Enums\LedgerTransactionType::cases() as $case)
                    <flux:select.option :value="$case->value">{{ __($case->label()) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="accountId" :label="__('Account')">
                <flux:select.option value="">{{ __('All accounts') }}</flux:select.option>
                @foreach ($this->accounts as $account)
                    <flux:select.option :value="$account->id">{{ __($account->account_type->label()) }} {{ $account->maskedNumber() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div></div>
            <flux:input wire:model.live="from" :label="__('From')" type="date" />
            <flux:input wire:model.live="to" :label="__('To')" type="date" />
            <flux:input wire:model.live.debounce.500ms="minAmount" :label="__('Min amount')" type="number" step="0.01" min="0" />
            <flux:input wire:model.live.debounce.500ms="maxAmount" :label="__('Max amount')" type="number" step="0.01" min="0" />
        </div>
        @error('to') <flux:text class="mt-2 text-red-600">{{ $message }}</flux:text> @enderror
        <flux:button class="mt-4" size="sm" variant="ghost" wire:click="resetFilters">{{ __('Reset filters') }}</flux:button>
    </flux:card>

    <flux:card wire:loading.class="opacity-60">
        @if ($this->transactions->isEmpty())
            <flux:heading size="lg">{{ __('No transactions found') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Posted transactions will appear here. Pending transfer requests are listed under Transfers.') }}</flux:text>
        @else
            <flux:table :paginate="$this->transactions">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Description') }}</flux:table.column>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                    <flux:table.column>{{ __('Account') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->transactions as $tx)
                        <flux:table.row wire:key="tx-{{ $tx->id }}">
                            <flux:table.cell>{{ $tx->posted_at->timezone(auth()->user()->displayTimezone())->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell>{{ __($tx->type->label()) }}</flux:table.cell>
                            <flux:table.cell>{{ $tx->description }}</flux:table.cell>
                            <flux:table.cell>
                                <button type="button" wire:click="select({{ $tx->id }})" x-on:click="$flux.modal('transaction-details').show()" class="cursor-pointer text-trb-blue underline">{{ $tx->reference }}</button>
                            </flux:table.cell>
                            <flux:table.cell>{{ $tx->account->maskedNumber() }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">
                                {{ $tx->direction === \App\Enums\LedgerDirection::Credit ? '+' : '−' }}{{ \App\Services\Money::format($tx->amount_minor, $tx->currency) }}
                                <span class="text-xs text-zinc-500">({{ __($tx->direction->label()) }})</span>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" color="green">{{ __('Posted') }}</flux:badge></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <flux:modal name="transaction-details" class="w-full max-w-md">
        @if ($this->selected)
            <flux:heading size="lg">{{ __('Transaction details') }}</flux:heading>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-zinc-500">{{ __('Reference') }}</dt>
                <dd>{{ $this->selected->reference }}</dd>
                <dt class="text-zinc-500">{{ __('Type') }}</dt>
                <dd>{{ __($this->selected->type->label()) }} ({{ __($this->selected->direction->label()) }})</dd>
                <dt class="text-zinc-500">{{ __('Amount') }}</dt>
                <dd class="tabular-nums">{{ \App\Services\Money::format($this->selected->amount_minor, $this->selected->currency) }}</dd>
                <dt class="text-zinc-500">{{ __('Account') }}</dt>
                <dd>{{ $this->selected->account->maskedNumber() }}</dd>
                <dt class="text-zinc-500">{{ __('Posted') }}</dt>
                <dd>{{ $this->selected->posted_at->timezone(auth()->user()->displayTimezone())->format('M j, Y g:i A') }}</dd>
                <dt class="text-zinc-500">{{ __('Description') }}</dt>
                <dd>{{ $this->selected->description ?? '—' }}</dd>
            </dl>
        @endif
    </flux:modal>
</div>
