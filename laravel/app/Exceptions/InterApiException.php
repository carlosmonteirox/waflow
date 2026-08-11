<?php

namespace App\Exceptions;

use RuntimeException;

class InterApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 502,
        public readonly ?string $providerCode = null,
    ) {
        parent::__construct($message);
    }
}
