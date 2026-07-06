<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

require ROOT_PATH . '/vendor/autoload.php';

use AvatarTok\Core\Application;
use AvatarTok\Core\Request;
use AvatarTok\Core\Response;

$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->safeLoad();

$app = new Application();

try {
    $request  = Request::fromGlobals();
    $response = $app->handle($request);
    $response->send();
} catch (Throwable $e) {
    $code    = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
    $message = $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'Internal Server Error';

    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message, 'code' => $code]);
}
