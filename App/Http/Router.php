<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\ClassNotFoundException;
use App\Exceptions\InvalidMiddlewareException;
use App\Exceptions\RouteHandlerException;

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
        $middlewareResponse = $this->runMiddlewares($route['middlewares'], $request);
        if ($middlewareResponse !== null) {
            return $middlewareResponse;
        }

        $result = $this->invokeHandler($route['handler'], $request);

        return $this->toResponse($result);
    }

    /**
     * @param array<int, class-string|object> $middlewares
     */
    private function runMiddlewares(array $middlewares, Request $request): ?Response
    {
        foreach ($middlewares as $middleware) {
            $middlewareInstance = is_object($middleware) ? $middleware : $this->resolve($middleware);

            if (!method_exists($middlewareInstance, 'handle')) {
                throw new InvalidMiddlewareException('Middleware ' . get_class($middlewareInstance) . ' must have a handle() method.');
            }

            $response = $middlewareInstance->handle($request);
            if ($response instanceof Response) {
                return $response;
            }
        }

        return null;
    }

    /**
     * @param callable|array{0: class-string|object, 1: string} $handler
     */
    private function invokeHandler(callable|array $handler, Request $request): mixed
    {
        if (is_callable($handler) && !is_array($handler)) {
            return $handler($request);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controllerTarget, $action] = $handler;
            $controllerInstance = is_object($controllerTarget) ? $controllerTarget : $this->resolve($controllerTarget);

            if (!method_exists($controllerInstance, $action)) {
                throw new RouteHandlerException("Action {$action} not found on controller " . get_class($controllerInstance));
            }

            return $controllerInstance->$action($request);
        }

        throw new RouteHandlerException('Invalid route handler provided.');
    }

    private function toResponse(mixed $result): Response
    {
        return match (true) {
            $result instanceof Response => $result,
            is_string($result) => Response::text($result),
            is_array($result) => Response::json($result),
            default => new Response('', 204),
        };
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
            throw new ClassNotFoundException("Target class {$class} does not exist.");
        }

        return new $class();
    }

    private function normalizePath(string $path): string
    {
        $trimmed = '/' . trim($path, '/');

        return $trimmed === '//' ? '/' : $trimmed;
    }
}
