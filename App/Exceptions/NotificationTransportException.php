<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Indicates a transport preparation failure before a push request is attempted. */
final class NotificationTransportException extends \RuntimeException {}
