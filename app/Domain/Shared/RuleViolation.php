<?php

namespace App\Domain\Shared;

use DomainException;

final class RuleViolation extends DomainException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
