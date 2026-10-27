<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     */
    public function __construct(
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $server = [],
        private readonly array $cookies = []
    ) {}

    public static function createFromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_COOKIE);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function uri(): string
    {
        $rawUri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($rawUri, PHP_URL_PATH);

        if ($path === '') {
            return '/';
        }

        // Normalize base URL prefix if needed
        return '/' . ltrim($path, '/');
    }

    public function isAjax(): bool
    {
        $header = (string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '');

        return strtolower($header) === 'xmlhttprequest';
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function queryString(string $key, string $default = ''): string
    {
        $value = $this->query($key, $default);

        return is_string($value) ? trim($value) : $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }

        return $this->post[$key] ?? $default;
    }

    public function postString(string $key, string $default = ''): string
    {
        $value = $this->post($key, $default);

        return is_string($value) ? trim($value) : $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function inputString(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_string($value) ? trim($value) : $default;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        $value = $this->cookies[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function cookieString(string $key, string $default = ''): string
    {
        $value = $this->cookie($key, $default);

        return $value !== null ? trim($value) : $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($this->server[$serverKey]) && is_string($this->server[$serverKey])
            ? $this->server[$serverKey]
            : $default;
    }
}
