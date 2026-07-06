<?php

declare(strict_types=1);

namespace AvatarTok\Core;

use AvatarTok\Exceptions\HttpException;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $group = new RouteGroup($prefix, $middleware);
        $callback($group);

        foreach ($group->getRoutes() as $route) {
            $this->routes[] = $route;
        }
    }

    private function addRoute(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'handler'    => $handler,
            'middleware' => $middleware,
            'regex'      => $this->buildRegex($path),
            'params'     => $this->extractParamNames($path),
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $uri    = rtrim(strtok($request->getUri(), '?'), '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['regex'], $uri, $matches)) {
                continue;
            }

            $params = [];
            foreach ($route['params'] as $i => $name) {
                $params[$name] = $matches[$i + 1] ?? null;
            }
            $request->setRouteParams($params);

            foreach ($route['middleware'] as $mw) {
                $early = (new $mw())->process($request);
                if ($early instanceof Response) {
                    return $early;
                }
            }

            [$controllerClass, $action] = $route['handler'];
            $controller = new $controllerClass();

            return $controller->$action($request);
        }

        throw new HttpException(404, "Route not found: {$method} {$uri}");
    }

    private function buildRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function extractParamNames(string $path): array
    {
        preg_match_all('/\{([a-zA-Z_]+)\}/', $path, $matches);
        return $matches[1];
    }
}
