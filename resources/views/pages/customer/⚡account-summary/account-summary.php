<?php

use App\Models\Account;
use App\Services\AccountBalance;
use App\Services\AccountBalanceCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

return new #[Title('Account Summary')] class extends Component
{
    /**
     * @return array<int, array{account: Account, balance: AccountBalance}>
     */
    #[Computed]
    public function accounts(): array
    {
        $calculator = app(AccountBalanceCalculator::class);

        return Auth::user()->accounts()->orderBy('id')->get()
            ->map(fn ($account) => ['account' => $account, 'balance' => $calculator->forAccount($account)])
            ->all();
    }
};
