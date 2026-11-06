<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\CustomerService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    use NotifiesServiceErrors;

    protected static string $resource = UserResource::class;

    /**
     * Create the customer through the audited service; a password-setup link is emailed.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $customer = null;

        if (! $this->attempt(function () use ($data, &$customer): void {
            /** @var User $actor */
            $actor = auth()->user();
            $customer = app(CustomerService::class)->create($data, $actor, $data['reason']);
        })) {
            $this->halt();
        }

        return $customer;
    }
}
