<?php

declare(strict_types=1);

namespace AvatarTok\Exceptions;

class ValidationException extends HttpException
{
    public function __construct(private readonly array $errors)
    {
        parent::__construct(422, 'Validation failed.');
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
