<?php

namespace Database\Seeders;

use App\Enums\HoldStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerTransactionType;
use App\Enums\RoleName;
use App\Models\Account;
use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fictional demo data for local development only. Nothing here represents real funds.
 * Demo login: demo.customer@example.test / password (local development only).
 */
class DemoDataSeeder extends Seeder
{
    private const EMAIL = 'demo.customer@example.test';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The demo seeder may only run in local or testing environments.');
        }

        $this->call(RolesAndPermissionsSeeder::class);

        DB::transaction(function (): void {
            $customer = User::query()->firstOrCreate(
                ['email' => self::EMAIL],
                ['name' => 'John Doe (DEMO)', 'password' => 'password'],
            );

            if (! str_contains($customer->name, '(DEMO)')) {
                throw new RuntimeException('A non-demo user already uses '.self::EMAIL.'; refusing to modify it.');
            }

            if ($customer->wasRecentlyCreated) {
                $customer->forceFill(['email_verified_at' => now()])->save();
                $customer->syncRoles(RoleName::Customer->value);
            }

            $customer->preference()->firstOrCreate();

            $account = Account::query()->firstOrCreate(
                ['user_id' => $customer->id],
                ['account_number' => '9000000001', 'account_type' => 'savings', 'currency' => 'USD', 'status' => 'active', 'opened_at' => now()->subYear()],
            );

            $this->post($account, 'DEMO-CR-1', LedgerTransactionType::Deposit, LedgerDirection::Credit, 500_000, 'Demo deposit', now()->subDays(10));
            $this->post($account, 'DEMO-DR-1', LedgerTransactionType::Withdrawal, LedgerDirection::Debit, 75_000, 'Demo withdrawal', now()->subDays(3));

            foreach (['Pending Deposit' => 50_000, 'Transfer Review' => 25_000] as $reason => $amount) {
                FundsHold::query()->firstOrCreate(
                    ['reference' => 'DEMO-HLD-'.strtoupper(substr(md5($reason), 0, 6))],
                    [
                        'account_id' => $account->id,
                        'customer_id' => $customer->id,
                        'reason' => $reason,
                        'amount_minor' => $amount,
                        'remaining_amount_minor' => $amount,
                        'currency' => 'USD',
                        'status' => HoldStatus::Active,
                    ],
                );
            }

            Transfer::query()->firstOrCreate(
                ['reference' => 'DEMO-TRF-1'],
                [
                    'customer_id' => $customer->id,
                    'source_account_id' => $account->id,
                    'beneficiary_name' => 'Demo Beneficiary',
                    'type' => 'outgoing_request',
                    'amount_minor' => 10_000,
                    'currency' => 'USD',
                    'description' => 'Demo pending transfer request',
                    'status' => 'pending',
                    'submitted_at' => now(),
                ],
            );
        });
    }

    private function post(Account $account, string $reference, LedgerTransactionType $type, LedgerDirection $direction, int $amount, string $description, \DateTimeInterface $postedAt): void
    {
        LedgerTransaction::query()->firstOrCreate(['reference' => $reference], [
            'account_id' => $account->id,
            'type' => $type,
            'direction' => $direction,
            'amount_minor' => $amount,
            'currency' => $account->currency,
            'description' => $description,
            'posted_at' => $postedAt,
        ]);
    }
}
