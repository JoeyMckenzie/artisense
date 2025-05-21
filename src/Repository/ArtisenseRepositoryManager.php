<?php

declare(strict_types=1);

namespace Artisense\Repository;

use Artisense\Support\DiskManager;
use Artisense\Support\VersionManager;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Filesystem\Filesystem;

final readonly class ArtisenseRepositoryManager
{
    public function __construct(
        private DiskManager $disk,
        private Config $config,
        private Filesystem $files,
        private ConnectionResolverInterface $resolver,
        private VersionManager $versionManager,
    ) {
        //
    }

    public function newConnection(): ArtisenseRepository
    {
        $dbPath = $this->disk->path('artisense.sqlite');

        // Ensure DB directory exists
        $this->files->ensureDirectoryExists(dirname($dbPath));

        if (! $this->files->exists($dbPath)) {
            $this->files->put($dbPath, '');
        }

        // Set up SQLite connection
        $this->config->set([
            'database.connections.artisense' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
            ],
        ]);

        $connection = $this->resolver->connection('artisense');

        return new ArtisenseRepository($connection, $this->versionManager);
    }
}
