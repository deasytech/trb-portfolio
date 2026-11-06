<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\LedgerTransactionType;
use Database\Factories\LedgerTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Posted ledger rows are immutable: corrections are compensating rows.
 *
 * @property int $id
 * @property int $account_id
 * @property string $reference
 * @property LedgerTransactionType $type
 * @property LedgerDirection $direction
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $description
 * @property int|null $reverses_id
 * @property Carbon $posted_at
 */
#[Fillable(['account_id', 'reference', 'type', 'direction', 'amount_minor', 'currency', 'description', 'related_type', 'related_id', 'reverses_id', 'posted_at', 'created_by'])]
class LedgerTransaction extends Model
{
    /** @use HasFactory<LedgerTransactionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Posted ledger transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Posted ledger transactions cannot be deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LedgerTransactionType::class,
            'direction' => LedgerDirection::class,
            'amount_minor' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * Amount with its sign: credits positive, debits negative.
     */
    public function signedAmountMinor(): int
    {
        return $this->direction === LedgerDirection::Credit ? $this->amount_minor : -$this->amount_minor;
    }
}
