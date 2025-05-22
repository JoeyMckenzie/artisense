<?php

declare(strict_types=1);

namespace Artisense\Exceptions;

use Exception;

final class DocumentationVersionException extends Exception
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function invalidVersion(): self
    {
        return new self("Documentation version must be a valid version string (e.g., '12.x', '11.x', 'master', etc.).");
    }

    public static function missingVersion(): self
    {
        return new self('Documentation version must be configured in your config file.');
    }
}
