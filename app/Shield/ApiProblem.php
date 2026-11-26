<?php

namespace App\Shield;

use RuntimeException;

final class ApiProblem extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($errorCode);
    }
}
