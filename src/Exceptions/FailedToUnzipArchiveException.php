<?php

declare(strict_types=1);

namespace Artisense\Exceptions;

use Exception;

final class FailedToUnzipArchiveException extends Exception
{
    public function __construct(string $message = 'Failed to unzip archive.')
    {
        parent::__construct($message);
    }
}
