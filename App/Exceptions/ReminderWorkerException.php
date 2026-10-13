<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Indicates invalid worker configuration or an operational coordination failure. */
final class ReminderWorkerException extends \RuntimeException {}
