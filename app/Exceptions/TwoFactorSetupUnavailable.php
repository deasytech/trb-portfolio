<?php

namespace App\Exceptions;

use RuntimeException;

class TwoFactorSetupUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Two-factor setup secret is not available.');
    }
}
