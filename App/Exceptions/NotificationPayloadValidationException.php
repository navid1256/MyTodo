<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Indicates that a notification payload cannot safely be sent to a browser. */
final class NotificationPayloadValidationException extends \InvalidArgumentException {}
