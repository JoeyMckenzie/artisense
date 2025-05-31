<?php

declare(strict_types=1);

namespace Artisense\Actions;

use Artisense\Contracts\Actions\CleanupArtifactsActionContract;
use Artisense\Support\StorageManager;
use Illuminate\Filesystem\Filesystem as Files;

final readonly class CleanupArtifactsAction implements CleanupArtifactsActionContract
{
    public function __construct(
        private Files $files,
        private StorageManager $storage,
    ) {
        //
    }

    public function handle(): void
    {
        $this->files->deleteDirectory($this->storage->docsPath);
        $this->files->deleteDirectory($this->storage->zipsPath);
    }
}
