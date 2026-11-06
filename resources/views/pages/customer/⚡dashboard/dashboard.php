<?php

use App\Enums\HoldStatus;
use App\Models\FundsHold;
use App\Models\Transfer;
use App\Services\PortfolioSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

return new #[Title('Dashboard')] class extends Component {
    /**
     * @return Collection<string, array<string, int|string>>
     */
    #[Computed]
    public function totals(): Collection
    {
        return app(PortfolioSummary::class)->forCustomer(Auth::user());
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->totals->sum('pending_count');
    }

    #[Computed]
    public function primaryAccount()
    {
        return Auth::user()->accounts()->orderBy('id')->first();
    }

    #[Computed]
    public function recentTransfers()
    {
        return Transfer::query()->where('customer_id', Auth::id())->latest('id')->limit(5)->get();
    }

    #[Computed]
    public function activeHolds()
    {
        return FundsHold::query()->where('customer_id', Auth::id())->where('status', HoldStatus::Active)->latest('id')->limit(5)->get();
    }
};
