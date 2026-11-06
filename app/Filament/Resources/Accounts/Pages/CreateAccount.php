<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Enums\AccountType;
use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Models\User;
use App\Services\AccountService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAccount extends CreateRecord
{
    use NotifiesServiceErrors;

    protected static string $resource = AccountResource::class;

    /**
     * Open the account through the audited service; balances start at zero.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $account = null;

        if (! $this->attempt(function () use ($data, &$account): void {
            /** @var User $actor */
            $actor = auth()->user();
            $customer = User::query()->findOrFail($data['user_id']);
            $account = app(AccountService::class)->open($customer, AccountType::from($data['account_type']), $data['currency'], $actor, $data['reason']);
        })) {
            $this->halt();
        }

        return $account;
    }
}
