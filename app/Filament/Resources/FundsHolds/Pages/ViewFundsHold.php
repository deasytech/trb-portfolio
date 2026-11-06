<?php

namespace App\Filament\Resources\FundsHolds\Pages;

use App\Enums\HoldStatus;
use App\Enums\PermissionName;
use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\FundsHolds\FundsHoldResource;
use App\Models\FundsHold;
use App\Services\HoldService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

/**
 * @extends ViewRecord<FundsHold>
 */
class ViewFundsHold extends ViewRecord
{
    use NotifiesServiceErrors;

    protected static string $resource = FundsHoldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit')
                ->fillForm(fn (): array => ['hold_reason' => $this->getRecord()->reason, 'expires_at' => $this->getRecord()->expires_at])
                ->schema([
                    Textarea::make('hold_reason')->label('Hold reason')->required()->maxLength(500),
                    DateTimePicker::make('expires_at')->after('now'),
                    Textarea::make('reason')->label('Reason for this change')->required()->maxLength(500),
                ])
                ->visible(fn (): bool => auth()->user()->can(PermissionName::PlaceHolds->value) && $this->getRecord()->effectiveStatus() === HoldStatus::Active)
                ->action(function (array $data): void {
                    /** @var FundsHold $hold */
                    $hold = $this->getRecord();
                    $expires = filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null;

                    if (! $this->attempt(fn () => app(HoldService::class)->update($hold, auth()->user(), $data['hold_reason'], $expires, $data['reason']))) {
                        return;
                    }

                    $hold->refresh();
                    $this->refreshFormData(['reason', 'expires_at']);
                    Notification::make()->title('Hold updated')->success()->send();
                }),
            Action::make('release')
                ->label('Release (remove) hold')
                ->color('success')
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->required()->maxLength(500)])
                ->visible(fn (): bool => auth()->user()->can(PermissionName::ReleaseHolds->value) && $this->getRecord()->effectiveStatus() === HoldStatus::Active)
                ->action(function (array $data): void {
                    /** @var FundsHold $hold */
                    $hold = $this->getRecord();
                    app(HoldService::class)->release($hold, auth()->user(), $data['reason']);
                    $hold->refresh();
                    $this->refreshFormData(['status', 'remaining_amount_minor']);
                    Notification::make()->title('Hold released')->success()->send();
                }),
        ];
    }
}
