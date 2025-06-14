<?php

declare(strict_types=1);

namespace Artisense\Tests;

use Artisense\ArtisenseServiceProvider;
use Artisense\Enums\DocumentationVersion;
use Artisense\Enums\SearchPreference;
use Artisense\Formatters\BasicMarkdownFormatter;
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

    /** @var DocumentationVersion|DocumentationVersion[] */
    protected DocumentationVersion|array $version;

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
        Config::set('artisense.versions', $this->version);
        Config::set('artisense.formatter', BasicMarkdownFormatter::class);
        Config::set('artisense.search.preference', SearchPreference::ORDERED);
        Config::set('artisense.search.proximity', 5);
        Config::set('artisense.retain_artifacts', true);
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
