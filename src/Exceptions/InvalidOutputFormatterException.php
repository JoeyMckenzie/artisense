<?php

declare(strict_types=1);

namespace Artisense\Exceptions;

use Artisense\Contracts\OutputFormatterContract;
use Exception;

final class InvalidOutputFormatterException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function invalidFormatterClass(string $className): self
    {
        return new self("The class '$className' is not a valid output formatter.");
    }

    public static function mustInheritFromOutputFormatter(string $className): self
    {
        return new self(sprintf('The class %s must inherit from %s.', $className, OutputFormatterContract::class));
    }
}
