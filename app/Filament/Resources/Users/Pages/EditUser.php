<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\CustomerService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<User>
 */
class EditUser extends EditRecord
{
    use NotifiesServiceErrors;

    protected static string $resource = UserResource::class;

    /**
     * Update the customer through the audited service.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $updated = $record;

        if (! $this->attempt(function () use ($record, $data, &$updated): void {
            /** @var User $actor */
            $actor = auth()->user();
            $updated = app(CustomerService::class)->update($record, $data, $actor, $data['reason']);
        })) {
            $this->halt();
        }

        return $updated;
    }
}
