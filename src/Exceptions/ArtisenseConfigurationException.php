<?php

declare(strict_types=1);

namespace Artisense\Exceptions;

use Exception;

final class ArtisenseConfigurationException extends Exception
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function invalidConfiguration(string $message): self
    {
        return new self("Configuration is invalid: $message");
    }
}
