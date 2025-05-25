<?php

declare(strict_types=1);

namespace Artisense\Exceptions;

use Exception;

final class ArtisenseException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
