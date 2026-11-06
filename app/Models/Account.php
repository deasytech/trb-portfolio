<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $account_number
 * @property string $account_number_last_four
 * @property AccountType $account_type
 * @property string $currency
 * @property AccountStatus $status
 * @property Carbon|null $opened_at
 */
#[Fillable(['user_id', 'account_number', 'account_type', 'currency', 'status', 'opened_at'])]
#[Hidden(['account_number'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * Setting the number also derives its last four digits, independent of model events.
     *
     * @return Attribute<string, string>
     */
    protected function accountNumber(): Attribute
    {
        return Attribute::set(fn (string $value): array => [
            'account_number' => $value,
            'account_number_last_four' => substr($value, -4),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'status' => AccountStatus::class,
            'opened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<LedgerTransaction, $this>
     */
    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    /**
     * @return HasMany<FundsHold, $this>
     */
    public function holds(): HasMany
    {
        return $this->hasMany(FundsHold::class);
    }

    /**
     * Masked identifier safe for display and logs.
     */
    public function maskedNumber(): string
    {
        return '•••• '.$this->account_number_last_four;
    }
}
