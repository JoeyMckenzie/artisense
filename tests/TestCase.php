<?php

declare(strict_types=1);

namespace Artisense\Tests;

use Artisense\ArtisenseServiceProvider;
use Artisense\Enums\DocumentationVersion;
use Artisense\Support\DiskManager;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
    }

    protected function tearDownParallelTestingCallbacks(): void
    {
        $this->app?->forgetInstance(DiskManager::class);
        File::deleteDirectory($this->storagePath);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ArtisenseServiceProvider::class,
        ];
    }

    protected function setUpParallelTestingCallbacks(): void
    {
        self::setUpVersion();
        self::setUpStorage();
        self::setUpTestDatabase();
    }

    private function setUpVersion(): void
    {
        $this->version = DocumentationVersion::VERSION_12;
        Config::set('artisense.version', $this->version);
    }

    private function setUpStorage(): void
    {
        $uniqueKey = 'artisense/test-' . Str::ulid();
        $this->storagePath = storage_path($uniqueKey);
        $this->app?->bind(DiskManager::class, fn(): DiskManager => new DiskManager($uniqueKey));
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
