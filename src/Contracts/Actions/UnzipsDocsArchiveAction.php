<?php

declare(strict_types=1);

namespace Artisense\Contracts\Actions;

use Artisense\Exceptions\FailedToUnzipArchiveException;

interface UnzipsDocsArchiveAction
{
    /**
     * @throws FailedToUnzipArchiveException
     */
    public function handle(string $extractedZipPath, string $extractPath): void;
}
