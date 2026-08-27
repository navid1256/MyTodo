<?php

declare(strict_types=1);

namespace App\Http;

use Closure;
use InvalidArgumentException;
use RuntimeException;

final class Router
{
    /**
     * @var array<int, array{
     *     method: string,
     *     path: string,
     *     handler: callable|array{0: class-string|object, 1: string},
     *     middlewares: array<int, class-string|object>
     * }>
     */
    private array $routes = [];

    /**
     * @var array<string, object>
     */
    private array $container = [];

    public function bind(string $abstract, object $instance): self
    {
        $this->container[$abstract] = $instance;

        return $this;
    }

    /**
     * @param callable|array{0: class-string|object, 1: string} $handler
     * @param array<int, class-string|object> $middlewares
     */
    public function get(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->add('GET', $path, $handler, $middlewares);
    }

    /**
     * @param callable|array{0: class-string|object, 1: string} $handler
     * @param array<int, class-string|object> $middlewares
     */
    public function post(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->add('POST', $path, $handler, $middlewares);
    }

    /**
     * @param callable|array{0: class-string|object, 1: string} $handler
     * @param array<int, class-string|object> $middlewares
     */
    public function add(string $method, string $path, callable|array $handler, array $middlewares = []): self
    {
        $normalizedPath = $this->normalizePath($path);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $normalizedPath,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $this->normalizePath($request->uri());

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $uri) {
                return $this->executeRoute($route, $request);
            }
        }

        if ($request->isAjax()) {
            return Response::json([
                'success' => false,
                'message' => 'Route not found: ' . $uri,
            ], 404);
        }

        return Response::text("404 Not Found: {$method} {$uri}", 404);
    }

    /**
     * @param array{
     *     method: string,
     *     path: string,
     *     handler: callable|array{0: class-string|object, 1: string},
     *     middlewares: array<int, class-string|object>
     * } $route
     */
    private function executeRoute(array $route, Request $request): Response
    {
        // 1. Run middleware pipeline
        foreach ($route['middlewares'] as $middleware) {
            $middlewareInstance = is_object($middleware) ? $middleware : $this->resolve($middleware);

            if (!method_exists($middlewareInstance, 'handle')) {
                throw new RuntimeException("Middleware " . get_class($middlewareInstance) . " must have a handle() method.");
            }

            $response = $middlewareInstance->handle($request);
            if ($response instanceof Response) {
                return $response;
            }
        }

        // 2. Execute route handler
        $handler = $route['handler'];

        if (is_callable($handler) && !is_array($handler)) {
            $result = $handler($request);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$controllerTarget, $action] = $handler;
            $controllerInstance = is_object($controllerTarget) ? $controllerTarget : $this->resolve($controllerTarget);

            if (!method_exists($controllerInstance, $action)) {
                throw new RuntimeException("Action {$action} not found on controller " . get_class($controllerInstance));
            }

            $result = $controllerInstance->$action($request);
        } else {
            throw new InvalidArgumentException('Invalid route handler provided.');
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::text($result);
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return new Response('', 204);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function resolve(string $class): object
    {
        if (isset($this->container[$class])) {
            /** @var T */
            return $this->container[$class];
        }

        if (!class_exists($class)) {
            throw new RuntimeException("Target class {$class} does not exist.");
        }

        return new $class();
    }

    private function normalizePath(string $path): string
    {
        $trimmed = '/' . trim($path, '/');

        return $trimmed === '//' ? '/' : $trimmed;
    }
}
