<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class OpenWaException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 502,
        public readonly array $response = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
