<?php

namespace App\Exceptions;

use RuntimeException;

class CurseForgeException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
