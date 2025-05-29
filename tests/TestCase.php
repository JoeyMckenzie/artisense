<?php

declare(strict_types=1);

namespace Artisense\Tests;

use Artisense\ArtisenseServiceProvider;
use Artisense\Enums\DocumentationVersion;
use Artisense\Enums\SearchPreference;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

class TestCase extends Orchestra
{
    protected string $storagePath;

    protected string $dbPath {
        get => $this->storagePath . '/artisense.sqlite';
    }

    protected DocumentationVersion $version;

    protected ConnectionInterface $connection;

    protected Builder $db {
        get => $this->connection->table('docs');
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::setupConfiguration();
        self::setUpStorage();
        self::setUpTestDatabase();
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        File::deleteDirectory($this->storagePath);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ArtisenseServiceProvider::class,
        ];
    }

    private function setupConfiguration(): void
    {
        $this->version = DocumentationVersion::VERSION_12;
        Config::set('artisense.version', $this->version->value);
        Config::set('artisense.search.preference', SearchPreference::ORDERED);
        Config::set('artisense.search.proximity', 5);
    }

    private function setUpStorage(): void
    {
        $this->storagePath = storage_path('artisense');
    }

    private function setUpTestDatabase(): void
    {
        File::ensureDirectoryExists(dirname($this->dbPath));
        File::put($this->dbPath, '');

        Config::set([
            'database.connections.artisense' => [
                'driver' => 'sqlite',
                'database' => $this->dbPath,
                'prefix' => '',
            ],
        ]);

        $this->connection = DB::connection('artisense');
    }
}
