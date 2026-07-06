<?php

declare(strict_types=1);

namespace AvatarTok\Core;

class Response
{
    private array $headers = ['Content-Type' => 'application/json'];

    public function __construct(
        private mixed $data,
        private int   $status = 200
    ) {}

    public static function ok(mixed $data = null, string $message = 'Success'): self
    {
        return new self(['success' => true, 'message' => $message, 'data' => $data], 200);
    }

    public static function created(mixed $data = null, string $message = 'Created'): self
    {
        return new self(['success' => true, 'message' => $message, 'data' => $data], 201);
    }

    public static function noContent(): self
    {
        return new self(null, 204);
    }

    public static function error(string $message, int $status = 400, array $errors = []): self
    {
        $body = ['success' => false, 'message' => $message];
        if (!empty($errors)) {
            $body['errors'] = $errors;
        }
        return new self($body, $status);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): self
    {
        return new self([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
                'has_more'    => ($page * $perPage) < $total,
            ],
        ], 200);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->data !== null) {
            echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
