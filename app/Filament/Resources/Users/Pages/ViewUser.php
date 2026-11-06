<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\PermissionName;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\AdminActions;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @extends ViewRecord<User>
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction('suspend', 'Suspend customer', UserStatus::Suspended, 'danger', UserStatus::Active),
            $this->statusAction('reactivate', 'Reactivate customer', UserStatus::Active, 'success', UserStatus::Suspended),
        ];
    }

    private function statusAction(string $name, string $label, UserStatus $target, string $color, UserStatus $visibleWhen): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->required()->maxLength(500)])
            ->visible(fn (): bool => auth()->user()->can(PermissionName::SuspendCustomers->value) && $this->getRecord()->status === $visibleWhen)
            ->action(function (array $data) use ($target): void {
                /** @var User $customer */
                $customer = $this->getRecord();
                app(AdminActions::class)->setCustomerStatus($customer, $target, auth()->user(), $data['reason']);
                $this->refreshFormData(['status']);
                Notification::make()->title('Customer status updated')->success()->send();
            });
    }
}
