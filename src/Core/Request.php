<?php

declare(strict_types=1);

namespace AvatarTok\Core;

class Request
{
    private array $routeParams = [];
    private ?array $parsedBody = null;
    private ?object $authUser  = null;

    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array  $headers,
        private readonly array  $queryParams,
        private readonly string $rawBody,
        private readonly array  $files
    ) {}

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = $_SERVER['CONTENT_TYPE'];
        }

        return new self(
            method:      $_SERVER['REQUEST_METHOD'] ?? 'GET',
            uri:         $_SERVER['REQUEST_URI'] ?? '/',
            headers:     $headers,
            queryParams: $_GET,
            rawBody:     file_get_contents('php://input') ?: '',
            files:       $_FILES
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getHeader(string $name, string $default = ''): string
    {
        return $this->headers[strtoupper(str_replace('-', '_', $name))]
            ?? $this->headers[strtoupper($name)]
            ?? $default;
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->getHeader('AUTHORIZATION');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function body(): array
    {
        if ($this->parsedBody !== null) {
            return $this->parsedBody;
        }

        $contentType = $this->getHeader('CONTENT-TYPE');

        if (str_contains($contentType, 'application/json')) {
            $this->parsedBody = json_decode($this->rawBody, true) ?? [];
        } elseif (str_contains($contentType, 'multipart/form-data')) {
            $this->parsedBody = $_POST;
        } else {
            parse_str($this->rawBody, $parsed);
            $this->parsedBody = $parsed;
        }

        return $this->parsedBody;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body()[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->body(), array_flip($keys));
    }

    public function validate(array $rules): array
    {
        $data   = $this->body();
        $errors = [];

        foreach ($rules as $field => $rule) {
            $ruleList = explode('|', $rule);
            foreach ($ruleList as $r) {
                if ($r === 'required' && empty($data[$field])) {
                    $errors[$field][] = "Field '{$field}' is required.";
                }
                if (str_starts_with($r, 'min:')) {
                    $min = (int) substr($r, 4);
                    if (strlen((string)($data[$field] ?? '')) < $min) {
                        $errors[$field][] = "Field '{$field}' must be at least {$min} characters.";
                    }
                }
                if (str_starts_with($r, 'max:')) {
                    $max = (int) substr($r, 4);
                    if (strlen((string)($data[$field] ?? '')) > $max) {
                        $errors[$field][] = "Field '{$field}' must not exceed {$max} characters.";
                    }
                }
                if ($r === 'email' && !filter_var($data[$field] ?? '', FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "Field '{$field}' must be a valid email address.";
                }
                if ($r === 'numeric' && !is_numeric($data[$field] ?? '')) {
                    $errors[$field][] = "Field '{$field}' must be numeric.";
                }
            }
        }

        if (!empty($errors)) {
            throw new \AvatarTok\Exceptions\ValidationException($errors);
        }

        return array_intersect_key($data, $rules);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function setAuthUser(object $user): void
    {
        $this->authUser = $user;
    }

    public function user(): ?object
    {
        return $this->authUser;
    }

    public function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
