<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\JsonEncodingException;
use App\Exceptions\ViewNotFoundException;
use JsonException;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $statusCode = 200,
        private readonly array $headers = []
    ) {}

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public static function redirect(string $location, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $location]);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function text(string $body, int $statusCode = 200, array $headers = []): self
    {
        $mergedHeaders = array_merge(['Content-Type' => 'text/plain; charset=utf-8'], $headers);

        return new self($body, $statusCode, $mergedHeaders);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new JsonEncodingException('Failed to encode response data to JSON: ' . $exception->getMessage(), 0, $exception);
        }

        $mergedHeaders = array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers);

        return new self($json, $statusCode, $mergedHeaders);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function view(string $viewPath, array $data = [], int $statusCode = 200, array $headers = []): self
    {
        $resolvedPath = $viewPath;
        if (!is_file($resolvedPath)) {
            $basePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;
            $resolvedPath = $basePath . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $viewPath), DIRECTORY_SEPARATOR);
            if (!str_ends_with($resolvedPath, '.php')) {
                $resolvedPath .= '.php';
            }
        }

        if (!is_file($resolvedPath)) {
            throw new ViewNotFoundException("View template not found: {$viewPath}");
        }

        ob_start();
        extract($data, EXTR_SKIP);
        require_once $resolvedPath;
        $content = (string) ob_get_clean();

        $mergedHeaders = array_merge(['Content-Type' => 'text/html; charset=utf-8'], $headers);

        return new self($content, $statusCode, $mergedHeaders);
    }

    public function send(): never
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
        exit;
    }
}
