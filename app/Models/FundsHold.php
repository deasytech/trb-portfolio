<?php

namespace App\Models;

use App\Enums\HoldStatus;
use Database\Factories\FundsHoldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Holds are never deleted; release them instead.
 *
 * @property int $id
 * @property string $reference
 * @property int $account_id
 * @property int $customer_id
 * @property string $reason
 * @property int $amount_minor
 * @property int $remaining_amount_minor
 * @property string $currency
 * @property HoldStatus $status
 * @property int|null $related_transfer_id
 * @property Carbon|null $released_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['reference', 'account_id', 'customer_id', 'reason', 'amount_minor', 'remaining_amount_minor', 'currency', 'status', 'related_transfer_id', 'created_by', 'expires_at'])]
class FundsHold extends Model
{
    /** @use HasFactory<FundsHoldFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Funds holds cannot be deleted; release them instead.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HoldStatus::class,
            'amount_minor' => 'integer',
            'remaining_amount_minor' => 'integer',
            'released_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Holds that currently reserve funds: active and not past expiry.
     *
     * @param  Builder<FundsHold>  $query
     */
    public function scopeReserving(Builder $query): void
    {
        $query->where('status', HoldStatus::Active)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function relatedTransfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'related_transfer_id');
    }
}
