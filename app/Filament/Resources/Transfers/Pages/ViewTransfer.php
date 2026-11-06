<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Enums\PermissionName;
use App\Enums\TransferStatus;
use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\Transfer;
use App\Services\TransferService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

/**
 * @extends ViewRecord<Transfer>
 */
class ViewTransfer extends ViewRecord
{
    use NotifiesServiceErrors;

    protected static string $resource = TransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->editAction(),
            $this->lifecycleAction('cancel', 'Cancel (remove) transfer', 'danger', PermissionName::ManageTransfers, [TransferStatus::Pending], true,
                fn (TransferService $s, Transfer $t, array $d) => $s->cancelAsStaff($t, auth()->user(), $d['reason'])),
            $this->lifecycleAction('approve', 'Approve', 'success', PermissionName::ApproveTransfers, [TransferStatus::Pending], false,
                fn (TransferService $s, Transfer $t, array $d) => $s->approve($t, auth()->user())),
            $this->lifecycleAction('reject', 'Reject', 'danger', PermissionName::RejectTransfers, [TransferStatus::Pending, TransferStatus::Approved], true,
                fn (TransferService $s, Transfer $t, array $d) => $s->reject($t, auth()->user(), $d['reason'])),
            $this->lifecycleAction('process', 'Start processing', 'info', PermissionName::ProcessTransfers, [TransferStatus::Approved], false,
                fn (TransferService $s, Transfer $t, array $d) => $s->startProcessing($t, auth()->user())),
            $this->lifecycleAction('complete', 'Mark completed', 'success', PermissionName::ProcessTransfers, [TransferStatus::Approved, TransferStatus::Processing], false,
                fn (TransferService $s, Transfer $t, array $d) => $s->complete($t, auth()->user())),
            $this->lifecycleAction('fail', 'Mark failed', 'danger', PermissionName::ProcessTransfers, [TransferStatus::Approved, TransferStatus::Processing], true,
                fn (TransferService $s, Transfer $t, array $d) => $s->fail($t, auth()->user(), $d['reason'])),
            $this->lifecycleAction('reverse', 'Reverse', 'warning', PermissionName::ReverseTransfers, [TransferStatus::Completed], true,
                fn (TransferService $s, Transfer $t, array $d) => $s->reverse($t, auth()->user(), $d['reason'])),
        ];
    }

    /**
     * Edit descriptive details of a pending transfer; amounts and accounts are fixed.
     */
    private function editAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->fillForm(fn (): array => ['description' => $this->getRecord()->description, 'beneficiary_name' => $this->getRecord()->beneficiary_name])
            ->schema([
                TextInput::make('description')->maxLength(255),
                TextInput::make('beneficiary_name')->maxLength(120),
                Textarea::make('reason')->required()->maxLength(500),
            ])
            ->visible(fn (): bool => auth()->user()->can(PermissionName::ManageTransfers->value) && $this->getRecord()->status === TransferStatus::Pending)
            ->action(function (array $data): void {
                if (! $this->attempt(fn () => app(TransferService::class)->updateDetails($this->getRecord(), auth()->user(), $data, $data['reason']))) {
                    return;
                }

                $this->getRecord()->refresh();
                $this->refreshFormData(['description', 'beneficiary_name']);
                Notification::make()->title('Transfer updated')->success()->send();
            });
    }

    /**
     * @param  array<int, TransferStatus>  $visibleWhen
     */
    private function lifecycleAction(string $name, string $label, string $color, PermissionName $permission, array $visibleWhen, bool $needsReason, Closure $run): Action
    {
        $action = Action::make($name)
            ->label($label)
            ->color($color)
            ->requiresConfirmation()
            ->visible(fn (): bool => auth()->user()->can($permission->value) && in_array($this->getRecord()->status, $visibleWhen, true))
            ->action(function (array $data) use ($run, $label): void {
                try {
                    $run(app(TransferService::class), $this->getRecord(), $data);
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

                    return;
                }

                $this->getRecord()->refresh();
                $this->refreshFormData(['status']);
                Notification::make()->title("{$label}: done")->success()->send();
            });

        return $needsReason ? $action->schema([Textarea::make('reason')->required()->maxLength(500)]) : $action;
    }
}
