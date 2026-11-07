<?php

namespace App\Models;

use App\Enums\TransferStatus;
use App\Enums\TransferType;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property string|null $idempotency_key
 * @property string|null $request_fingerprint
 * @property int $customer_id
 * @property int|null $source_account_id
 * @property int|null $destination_account_id
 * @property string|null $beneficiary_name
 * @property string|null $destination_identifier
 * @property TransferType $type
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $description
 * @property TransferStatus $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $completed_at
 * @property string|null $rejection_reason
 */
#[Fillable(['reference', 'idempotency_key', 'customer_id', 'source_account_id', 'destination_account_id', 'beneficiary_name', 'destination_identifier', 'type', 'amount_minor', 'currency', 'description', 'status', 'submitted_at', 'created_by'])]
#[Hidden(['destination_identifier'])]
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransferType::class,
            'status' => TransferStatus::class,
            'amount_minor' => 'integer',
            'destination_identifier' => 'encrypted',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
