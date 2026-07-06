<?php

declare(strict_types=1);

namespace AvatarTok\Core;

use AvatarTok\Middleware\CorsMiddleware;
use AvatarTok\Middleware\RateLimitMiddleware;
use AvatarTok\Middleware\AuthMiddleware;
use AvatarTok\Routes\ApiRoutes;

class Application
{
    private Router $router;
    private array  $globalMiddleware = [];

    public function __construct()
    {
        $this->router = new Router();
        $this->globalMiddleware = [
            new CorsMiddleware(),
            new RateLimitMiddleware(),
        ];

        ApiRoutes::register($this->router);
    }

    public function handle(Request $request): Response
    {
        foreach ($this->globalMiddleware as $middleware) {
            $early = $middleware->process($request);
            if ($early instanceof Response) {
                return $early;
            }
        }

        return $this->router->dispatch($request);
    }
}
