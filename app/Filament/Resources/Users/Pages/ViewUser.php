<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\PermissionName;
use App\Enums\UserStatus;
use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\AdminActions;
use App\Services\CustomerService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @extends ViewRecord<User>
 */
class ViewUser extends ViewRecord
{
    use NotifiesServiceErrors;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->deleteAction(),
            $this->statusAction('suspend', 'Suspend customer', UserStatus::Suspended, 'danger', UserStatus::Active),
            $this->statusAction('reactivate', 'Reactivate customer', UserStatus::Active, 'success', UserStatus::Suspended),
        ];
    }

    /**
     * Delete a customer with no financial history, recording the reason.
     */
    private function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->required()->maxLength(500)])
            ->visible(fn (): bool => auth()->user()->can(PermissionName::ManageCustomers->value))
            ->action(function (array $data): void {
                /** @var User $customer */
                $customer = $this->getRecord();

                if (! $this->attempt(fn () => app(CustomerService::class)->delete($customer, auth()->user(), $data['reason']))) {
                    return;
                }

                Notification::make()->title('Customer deleted')->success()->send();
                $this->redirect(UserResource::getUrl('index'));
            });
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
                $customer->refresh();
                $this->refreshFormData(['status']);
                Notification::make()->title('Customer status updated')->success()->send();
            });
    }
}
