<?php

use App\Enums\HoldStatus;
use App\Models\FundsHold;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

return new #[Title('Funds On Hold')] class extends Component {
    use WithPagination;

    /**
     * Active (reserving) totals per currency.
     *
     * @return \Illuminate\Support\Collection<int, array{currency: string, held_minor: int}>
     */
    #[Computed]
    public function totals()
    {
        return FundsHold::query()->where('customer_id', Auth::id())->reserving()
            ->selectRaw('currency, SUM(remaining_amount_minor) AS held_minor')->groupBy('currency')->get()
            ->map(fn ($row) => ['currency' => $row->currency, 'held_minor' => (int) $row->held_minor]);
    }

    #[Computed]
    public function holds()
    {
        return FundsHold::query()->where('customer_id', Auth::id())
            ->with('relatedTransfer:id,reference')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [HoldStatus::Active->value])
            ->latest('id')
            ->paginate(10);
    }
};
