<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class UserSettingsValidationException extends InvalidArgumentException
{
    public function __construct(private readonly string $translationKey)
    {
        parent::__construct($translationKey);
    }

    public function translationKey(): string
    {
        return $this->translationKey;
    }
}
