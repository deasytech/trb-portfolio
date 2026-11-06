<?php

namespace App\Filament\Widgets;

use App\Enums\LedgerDirection;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\TransferStatus;
use App\Enums\UserStatus;
use App\Models\Account;
use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Operational snapshot. Money is always grouped by currency, never summed across currencies.
 */
class AdminOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Operations overview';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isActive() && $user->isStaff();
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $user = auth()->user();

        $stats = [
            Stat::make('Pending transfers', Transfer::query()->where('status', TransferStatus::Pending->value)->count())
                ->description('Awaiting approval'),
            Stat::make('In progress', Transfer::query()->whereIn('status', [TransferStatus::Approved->value, TransferStatus::Processing->value])->count())
                ->description('Approved or processing'),
            Stat::make('Active holds', FundsHold::query()->reserving()->count()),
            Stat::make('Active customers', User::query()->role(RoleName::Customer->value)->where('status', UserStatus::Active->value)->count())
                ->description(Account::query()->count().' accounts'),
        ];

        if ($user instanceof User && $user->can(PermissionName::ViewLedger->value)) {
            foreach ($this->ledgerBalancesByCurrency() as $currency => $balance) {
                $stats[] = Stat::make("Ledger balance ({$currency})", Money::format($balance, $currency));
            }

            foreach ($this->heldByCurrency() as $currency => $held) {
                $stats[] = Stat::make("Held funds ({$currency})", Money::format($held, $currency));
            }
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    private function ledgerBalancesByCurrency(): array
    {
        return LedgerTransaction::query()
            ->selectRaw('currency, SUM(CASE WHEN direction = ? THEN amount_minor ELSE -amount_minor END) AS balance', [LedgerDirection::Credit->value])
            ->groupBy('currency')
            ->orderBy('currency')
            ->pluck('balance', 'currency')
            ->map(fn (mixed $balance): int => (int) $balance)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function heldByCurrency(): array
    {
        return FundsHold::query()
            ->reserving()
            ->selectRaw('currency, SUM(remaining_amount_minor) AS held')
            ->groupBy('currency')
            ->orderBy('currency')
            ->pluck('held', 'currency')
            ->map(fn (mixed $held): int => (int) $held)
            ->all();
    }
}
