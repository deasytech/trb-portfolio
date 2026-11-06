<?php

namespace App\Filament\Resources\FundsHolds\Pages;

use App\Enums\HoldStatus;
use App\Enums\PermissionName;
use App\Filament\Resources\FundsHolds\FundsHoldResource;
use App\Models\FundsHold;
use App\Services\HoldService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @extends ViewRecord<FundsHold>
 */
class ViewFundsHold extends ViewRecord
{
    protected static string $resource = FundsHoldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('release')
                ->label('Release hold')
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
