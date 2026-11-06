<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use App\Services\StaffService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @extends ViewRecord<User>
 */
class ViewStaff extends ViewRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                    app(StaffService::class)->changeRole($this->staff(), RoleName::from($data['role']), $this->actor(), $data['reason']);
                    $this->refreshRecord();
                    Notification::make()->title('Role updated')->success()->send();
                }),
            $this->statusAction('deactivate', 'Deactivate', UserStatus::Suspended, 'danger', UserStatus::Active),
            $this->statusAction('activate', 'Reactivate', UserStatus::Active, 'success', UserStatus::Suspended),
        ];
    }

    private function statusAction(string $name, string $label, UserStatus $target, string $color, UserStatus $visibleWhen): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->required()->maxLength(500)])
            ->visible(fn (): bool => $this->canManage() && $this->staff()->status === $visibleWhen)
            ->action(function (array $data) use ($target): void {
                app(StaffService::class)->setStatus($this->staff(), $target, $this->actor(), $data['reason']);
                $this->refreshRecord();
                Notification::make()->title('Staff status updated')->success()->send();
            });
    }

    private function canManage(): bool
    {
        return $this->actor()->can(PermissionName::ManageStaff->value) && ! $this->staff()->is($this->actor());
    }

    private function refreshRecord(): void
    {
        $this->getRecord()->refresh();
        $this->refreshFormData(['status']);
    }

    private function staff(): User
    {
        /** @var User $staff */
        $staff = $this->getRecord();

        return $staff;
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
