<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use App\Services\StaffService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @extends ViewRecord<User>
 */
class ViewStaff extends ViewRecord
{
    use NotifiesServiceErrors;

    protected static string $resource = StaffResource::class;

    /**
     * Role change and activation actions, each requiring a reason.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('editDetails')
                ->label('Edit')
                ->fillForm(fn (): array => ['name' => $this->staff()->name, 'email' => $this->staff()->email])
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->email()->required()->maxLength(255),
                    Textarea::make('reason')->required()->maxLength(500),
                ])
                ->visible(fn (): bool => $this->actor()->can(PermissionName::ManageStaff->value))
                ->action(function (array $data): void {
                    if (! $this->attempt(fn () => app(StaffService::class)->update($this->staff(), $data['name'], $data['email'], $this->actor(), $data['reason']))) {
                        return;
                    }

                    $this->getRecord()->refresh();
                    $this->refreshFormData(['name', 'email']);
                    Notification::make()->title('Staff member updated')->success()->send();
                }),
            Action::make('resendSetupLink')
                ->label('Resend setup link')
                ->schema([Textarea::make('reason')->required()->maxLength(500)])
                ->visible(fn (): bool => $this->actor()->can(PermissionName::ManageStaff->value))
                ->action(function (array $data): void {
                    $sent = false;

                    if (! $this->attempt(function () use (&$sent, $data): void {
                        $sent = app(StaffService::class)->sendSetupLink($this->staff(), $this->actor(), $data['reason']);
                    })) {
                        return;
                    }

                    $sent
                        ? Notification::make()->title('Setup link sent')->success()->send()
                        : Notification::make()->title('The setup link could not be sent')->danger()->send();
                }),
            Action::make('deleteStaff')
                ->label('Delete')
                ->color('danger')
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->required()->maxLength(500)])
                ->visible(fn (): bool => $this->canManage())
                ->action(function (array $data): void {
                    if (! $this->attempt(fn () => app(StaffService::class)->delete($this->staff(), $this->actor(), $data['reason']))) {
                        return;
                    }

                    Notification::make()->title('Staff member deleted')->success()->send();
                    $this->redirect(StaffResource::getUrl('index'));
                }),
            Action::make('changeRole')
                ->label('Change role')
                ->schema([
                    Select::make('role')
                        ->options(collect(RoleName::staff())->mapWithKeys(fn (RoleName $role): array => [$role->value => str($role->value)->replace('_', ' ')->title()->toString()]))
                        ->required(),
                    Textarea::make('reason')->required()->maxLength(500),
                ])
                ->visible(fn (): bool => $this->canManage())
                ->action(function (array $data): void {
                    if (! $this->attempt(fn () => app(StaffService::class)->changeRole($this->staff(), RoleName::from($data['role']), $this->actor(), $data['reason']))) {
                        return;
                    }

                    $this->refreshRecord();
                    Notification::make()->title('Role updated')->success()->send();
                }),
            $this->statusAction('deactivate', 'Deactivate', UserStatus::Suspended, 'danger', UserStatus::Active),
            $this->statusAction('activate', 'Reactivate', UserStatus::Active, 'success', UserStatus::Suspended),
        ];
    }

    /**
     * Build a confirm-with-reason status action shown for one starting status.
     */
    private function statusAction(string $name, string $label, UserStatus $target, string $color, UserStatus $visibleWhen): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->required()->maxLength(500)])
            ->visible(fn (): bool => $this->canManage() && $this->staff()->status === $visibleWhen)
            ->action(function (array $data) use ($target): void {
                if (! $this->attempt(fn () => app(StaffService::class)->setStatus($this->staff(), $target, $this->actor(), $data['reason']))) {
                    return;
                }

                $this->refreshRecord();
                Notification::make()->title('Staff status updated')->success()->send();
            });
    }

    /**
     * Whether the current user may manage this staff member.
     */
    private function canManage(): bool
    {
        return $this->actor()->can(PermissionName::ManageStaff->value) && ! $this->staff()->is($this->actor());
    }

    /**
     * Reload the record and form after a change.
     */
    private function refreshRecord(): void
    {
        $this->getRecord()->refresh();
        $this->refreshFormData(['status']);
    }

    /**
     * The staff member being viewed.
     */
    private function staff(): User
    {
        /** @var User $staff */
        $staff = $this->getRecord();

        return $staff;
    }

    /**
     * The signed-in staff user.
     */
    private function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
