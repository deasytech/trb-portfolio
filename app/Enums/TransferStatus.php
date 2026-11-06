<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /**
     * Human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    /**
     * Statuses that have not reached a terminal state.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Approved, self::Processing];
    }

    /**
     * Badge colour used by the status-badge component.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved, self::Processing => 'blue',
            self::Completed => 'green',
            self::Rejected, self::Failed => 'red',
            self::Cancelled => 'zinc',
        };
    }
}
