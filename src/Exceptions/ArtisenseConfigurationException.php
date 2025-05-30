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
        return new self("Configuration is invalid: $message.");
    }

    public static function invalidVersion(): self
    {
        return new self("Documentation version must be a valid version string (e.g., '12.x', '11.x', 'master', etc.).");
    }

    public static function invalidVersions(): self
    {
        return new self("Documentation versions must be a valid version strings (e.g., '12.x', '11.x', 'master', etc.).");
    }

    public static function missingVersion(): self
    {
        return new self('Documentation version must be configured in your config file.');
    }

    public static function invalidPreference(): self
    {
        return new self('Invalid search preference. Must be either "ordered" or "unordered".');
    }

    public static function missingPreference(): self
    {
        return new self('Search preference must be configured in your config file.');
    }

    public static function invalidProximity(): self
    {
        return new self('Search proximity must be a positive integer.');
    }
}
