<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class PushSubscriptionValidationException extends InvalidArgumentException
{
    public function __construct(private readonly string $translationKey, private readonly int $statusCode = 422)
    {
        parent::__construct($translationKey);
    }

    public function translationKey(): string
    {
        return $this->translationKey;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
