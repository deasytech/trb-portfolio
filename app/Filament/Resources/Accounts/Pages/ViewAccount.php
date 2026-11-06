<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Enums\AccountStatus;
use App\Enums\PermissionName;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Services\AdminActions;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @extends ViewRecord<Account>
 */
class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction('freeze', 'Freeze account', AccountStatus::Frozen, 'danger', AccountStatus::Active),
            $this->statusAction('unfreeze', 'Unfreeze account', AccountStatus::Active, 'success', AccountStatus::Frozen),
        ];
    }

    private function statusAction(string $name, string $label, AccountStatus $target, string $color, AccountStatus $visibleWhen): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->required()->maxLength(500)])
            ->visible(fn (): bool => auth()->user()->can(PermissionName::FreezeAccounts->value) && $this->getRecord()->status === $visibleWhen)
            ->action(function (array $data) use ($target): void {
                /** @var Account $account */
                $account = $this->getRecord();
                app(AdminActions::class)->setAccountStatus($account, $target, auth()->user(), $data['reason']);
                $account->refresh();
                $this->refreshFormData(['status']);
                Notification::make()->title('Account status updated')->success()->send();
            });
    }
}
