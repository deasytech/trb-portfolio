<?php

namespace App\Enums;

enum TransferType: string
{
    case IncomingRequest = 'incoming_request';
    case Internal = 'internal';
    case OutgoingRequest = 'outgoing_request';

    /**
     * Human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::IncomingRequest => 'Incoming Request',
            self::Internal => 'Internal',
            self::OutgoingRequest => 'Outgoing Request',
        };
    }
}
