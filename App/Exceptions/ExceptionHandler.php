<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Response;
use ErrorException;
use Throwable;

final class ExceptionHandler
{
    private bool $debug = false;
    private bool $exceptionHandled = false;
    private ?string $logFile = null;

    public function __construct(private readonly string $rootPath) {}

    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');

        $this->configureLogFile();

        set_exception_handler([$this, 'handle']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
    }

    public function handle(Throwable $exception): never
    {
        $this->exceptionHandled = true;
        $this->report($exception);
        $this->clearOutputBuffers();

        if ($this->isJsonRequest()) {
            $payload = [
                'success' => false,
                'message' => $this->debug
                    ? $exception->getMessage()
                    : 'An unexpected error occurred. Please try again later.',
            ];

            if ($this->debug) {
                $payload['debug'] = [
                    'type' => $exception::class,
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            Response::json($payload, 500)->send();
        }

        $message = $this->debug
            ? (string) $exception
            : 'An unexpected error occurred. Please try again later.';

        Response::text($message, 500)->send();
    }

    public function handleShutdown(): void
    {
        if ($this->exceptionHandled) {
            return;
        }

        $error = error_get_last();
        if ($error === null || !in_array(
            $error['type'],
            [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR],
            true
        )) {
            return;
        }

        $this->handle(new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }

    private function report(Throwable $exception): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'));
        $requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $message = sprintf(
            "[%s] %s %s\n%s\n",
            gmdate('c'),
            $method,
            $requestPath,
            (string) $exception
        );

        if ($this->logFile !== null && error_log($message, 3, $this->logFile)) {
            return;
        }

        error_log($message);
    }

    private function configureLogFile(): void
    {
        $logDirectory = $this->rootPath
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'logs';

        if (!is_dir($logDirectory) && !mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
            return;
        }

        $this->logFile = $logDirectory . DIRECTORY_SEPARATOR . 'app.log';
        ini_set('error_log', $this->logFile);
    }

    private function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private function isJsonRequest(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');

        return str_contains($accept, 'application/json')
            || str_starts_with($path, '/api/');
    }
}
