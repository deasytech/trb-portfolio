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
    case Reversed = 'reversed';

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
            self::Reversed => 'Reversed',
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
            self::Cancelled, self::Reversed => 'zinc',
        };
    }

    /**
     * The single definition of the allowed lifecycle transitions.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Processing, self::Completed, self::Rejected, self::Failed],
            self::Processing => [self::Completed, self::Failed],
            self::Completed => [self::Reversed],
            self::Rejected, self::Cancelled, self::Failed, self::Reversed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
