<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\RoleName;
use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use App\Services\StaffService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    use NotifiesServiceErrors;

    protected static string $resource = StaffResource::class;

    /**
     * The add-staff action, which asks for an audit reason.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createStaff')
                ->label('Add staff member')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->email()->required()->maxLength(255),
                    Select::make('role')
                        ->options(collect(RoleName::staff())->mapWithKeys(fn (RoleName $role): array => [$role->value => str($role->value)->replace('_', ' ')->title()->toString()]))
                        ->required(),
                    Textarea::make('reason')->required()->maxLength(500),
                ])
                ->modalDescription('A password-setup link is emailed to the new staff member.')
                ->action(function (array $data, Action $action): void {
                    /** @var User $actor */
                    $actor = auth()->user();

                    $staff = null;

                    if (! $this->attempt(function () use ($data, $actor, &$staff): void {
                        $staff = app(StaffService::class)->create($data['name'], $data['email'], RoleName::from($data['role']), $actor, $data['reason']);
                    })) {
                        $action->halt();
                    }

                    if (! app(StaffService::class)->sendSetupLink($staff, $actor)) {
                        Notification::make()
                            ->title('Staff member added, but the password-setup link could not be sent')
                            ->body('Open the staff member and use "Resend setup link".')
                            ->warning()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()->title('Staff member added')->success()->send();
                }),
        ];
    }
}
