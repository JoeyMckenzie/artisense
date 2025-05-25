<?php

declare(strict_types=1);

namespace Artisense\Exceptions;

use Exception;

final class ArtisenseException extends Exception
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function from(string $message): self
    {
        return new self($message);
    }
}
