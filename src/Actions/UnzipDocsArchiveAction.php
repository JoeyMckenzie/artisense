<?php

declare(strict_types=1);

namespace Artisense\Actions;

use Artisense\Contracts\Actions\UnzipsDocsArchiveAction;
use Artisense\Exceptions\FailedToUnzipArchiveException;
use ZipArchive;

final class UnzipDocsArchiveAction implements UnzipsDocsArchiveAction
{
    /**
     * @throws FailedToUnzipArchiveException
     */
    public function handle(string $extractedZipPath, string $extractPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($extractedZipPath) !== true) {
            throw new FailedToUnzipArchiveException;
        }

        $zip->extractTo($extractPath);
        $zip->close();
    }
}
