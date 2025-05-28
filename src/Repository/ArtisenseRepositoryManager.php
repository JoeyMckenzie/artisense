<?php

declare(strict_types=1);

namespace Artisense\Repository;

use Artisense\Support\Services\StorageManager;
use Artisense\Support\Services\VersionManager;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Filesystem\Filesystem;

final readonly class ArtisenseRepositoryManager
{
    public function __construct(
        private StorageManager $disk,
        private Config $config,
        private Filesystem $files,
        private ConnectionResolverInterface $resolver,
        private VersionManager $versionManager,
    ) {
        //
    }

    public function newConnection(): ArtisenseRepository
    {
        $connection = $this->resolver->connection('artisense');

        return new ArtisenseRepository($connection, $this->versionManager);
    }

    public function initializeDatabase(): void
    {
        $this->configureDatabase();
        $this->createDocsTable();
    }

    private function configureDatabase(): void
    {
        $dbPath = $this->disk->path('artisense.sqlite');

        // Ensure DB directory exists
        $this->files->ensureDirectoryExists(dirname($dbPath));

        // TODO: This would be a real issue, would need to be handled
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
    }

    private function createDocsTable(): void
    {
        $db = $this->resolver->connection('artisense');
        $exists = $db->selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name='docs'");

        if ($exists === null) {
            $db->statement(
                <<<'SQL'
                    CREATE VIRTUAL TABLE docs USING fts5(
                        title, 
                        heading,
                        markdown,
                        content,
                        embedding,
                        path,
                        version,
                        link
                    );
                SQL
            );
        }
    }
}
