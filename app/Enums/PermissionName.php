<?php

namespace App\Enums;

enum PermissionName: string
{
    case ViewCustomers = 'customers.view';
    case ManageCustomers = 'customers.manage';
    case SuspendCustomers = 'customers.suspend';

    case ViewAccounts = 'accounts.view';
    case ManageAccounts = 'accounts.manage';
    case FreezeAccounts = 'accounts.freeze';
    case AdjustBalances = 'accounts.adjust';

    case ViewTransfers = 'transfers.view';
    case ApproveTransfers = 'transfers.approve';
    case RejectTransfers = 'transfers.reject';
    case ProcessTransfers = 'transfers.process';
    case ReverseTransfers = 'transfers.reverse';

    case ViewHolds = 'holds.view';
    case PlaceHolds = 'holds.place';
    case ReleaseHolds = 'holds.release';

    case ViewLedger = 'ledger.view';
    case ViewAuditLogs = 'audit.view';

    case ManageSettings = 'settings.manage';
    case ManageFinancialLimits = 'settings.financial-limits';
    case ManageStaff = 'staff.manage';

    /**
     * Permissions reserved for super administrators only.
     *
     * @return list<self>
     */
    public static function superAdministratorOnly(): array
    {
        return [
            self::AdjustBalances,
            self::ReverseTransfers,
            self::ManageFinancialLimits,
            self::ManageStaff,
        ];
    }
}
