<?php

declare(strict_types=1);

namespace Artisense\Support;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Filesystem\Filesystem as Files;

/**
 * @internal
 */
final readonly class DocumentationDatabaseManager
{
    public function __construct(
        private StorageManager $disk,
        private Files $files,
        private ConnectionResolverInterface $resolver,
    ) {
        //
    }

    public function initializeDatabase(): void
    {
        $this->configureDatabase();
        $this->createDocsTable();
    }

    private function configureDatabase(): void
    {
        $dbPath = $this->disk->dbPath;
        $this->files->ensureDirectoryExists(dirname($this->disk->dbPath));

        if (! $this->files->exists($dbPath)) {
            $this->files->put($dbPath, '');
        }
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
                        path,
                        version,
                        link
                    );
                SQL
            );
        }
    }
}
