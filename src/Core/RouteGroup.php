<?php

declare(strict_types=1);

namespace AvatarTok\Core;

class RouteGroup
{
    private array $routes = [];

    public function __construct(
        private readonly string $prefix,
        private readonly array  $groupMiddleware = []
    ) {}

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, array $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $this->prefix . $path,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
            'regex'      => $this->buildRegex($this->prefix . $path),
            'params'     => $this->extractParamNames($this->prefix . $path),
        ];
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    private function buildRegex(string $path): string
    {
        $path    = rtrim($path, '/') ?: '/';
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function extractParamNames(string $path): array
    {
        preg_match_all('/\{([a-zA-Z_]+)\}/', $path, $matches);
        return $matches[1];
    }
}
