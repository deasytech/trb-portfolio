<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Customer-facing totals, grouped by currency (amounts are never summed across currencies).
 */
class PortfolioSummary
{
    public function __construct(private readonly AccountBalanceCalculator $calculator) {}

    /**
     * @return Collection<string, array{currency: string, available_minor: int, held_minor: int, assets_minor: int, pending_minor: int, pending_count: int}>
     */
    public function forCustomer(User $user): Collection
    {
        $totals = collect();

        $blank = fn (string $currency): array => [
            'currency' => $currency,
            'available_minor' => 0,
            'held_minor' => 0,
            'assets_minor' => 0,
            'pending_minor' => 0,
            'pending_count' => 0,
        ];

        foreach ($user->accounts()->whereIn('status', [AccountStatus::Active, AccountStatus::Frozen])->get() as $account) {
            $balance = $this->calculator->forAccount($account);
            $row = $totals->get($account->currency, $blank($account->currency));
            // Frozen funds still count as assets and held, but are never available.
            if ($account->status === AccountStatus::Active) {
                $row['available_minor'] += $balance->availableMinor();
            }
            $row['held_minor'] += $balance->heldMinor;
            $row['assets_minor'] += $balance->ledgerMinor;
            $totals->put($account->currency, $row);
        }

        $pending = Transfer::query()
            ->where('customer_id', $user->id)
            ->whereIn('status', TransferStatus::open())
            ->selectRaw('currency, SUM(amount_minor) AS total, COUNT(*) AS aggregate_count')
            ->groupBy('currency')
            ->get();

        foreach ($pending as $group) {
            $row = $totals->get($group->currency, $blank($group->currency));
            $row['pending_minor'] = (int) $group->total;
            $row['pending_count'] = (int) $group->aggregate_count;
            $totals->put($group->currency, $row);
        }

        return $totals;
    }
}
