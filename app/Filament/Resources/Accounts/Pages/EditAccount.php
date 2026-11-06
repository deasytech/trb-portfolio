<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Enums\AccountType;
use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Models\User;
use App\Services\AccountService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<Account>
 */
class EditAccount extends EditRecord
{
    use NotifiesServiceErrors;

    protected static string $resource = AccountResource::class;

    /**
     * Change the account type through the audited service.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $updated = $record;

        if (! $this->attempt(function () use ($record, $data, &$updated): void {
            /** @var User $actor */
            $actor = auth()->user();
            $updated = app(AccountService::class)->update($record, AccountType::from($data['account_type']), $actor, $data['reason']);
        })) {
            $this->halt();
        }

        return $updated;
    }
}
