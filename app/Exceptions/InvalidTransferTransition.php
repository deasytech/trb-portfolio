<?php

namespace App\Exceptions;

use App\Enums\TransferStatus;
use DomainException;

class InvalidTransferTransition extends DomainException
{
    public static function between(TransferStatus $from, TransferStatus $to): self
    {
        return new self("A {$from->label()} transfer cannot become {$to->label()}.");
    }
}
