<?php

namespace App\Application\Errors;

use RuntimeException;

final class UseCaseError extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
