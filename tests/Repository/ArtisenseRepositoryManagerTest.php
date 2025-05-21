<?php

declare(strict_types=1);

namespace Artisense\Tests\Repository;

use Artisense\Repository\ArtisenseRepository;
use Artisense\Repository\ArtisenseRepositoryManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

covers(ArtisenseRepositoryManager::class);

describe(ArtisenseRepositoryManager::class, function (): void {
    beforeEach(function (): void {
        $this->repositoryManager = app(ArtisenseRepositoryManager::class);
    });

    it('creates a new connection and returns a repository instance', function (): void {
        // Act
        $repository = $this->repositoryManager->newConnection();

        // Assert
        expect($repository)->toBeInstanceOf(ArtisenseRepository::class);

        // Verify the SQLite file was created
        $dbPath = $this->storagePath.'/artisense.sqlite';
        expect(File::exists($dbPath))->toBeTrue();

        // Verify the connection was configured correctly
        $connectionConfig = Config::get('database.connections.artisense');
        expect($connectionConfig)->not->toBeNull()
            ->and($connectionConfig['driver'])->toBe('sqlite')
            ->and($connectionConfig['database'])->toBe($dbPath);
    });

    it('ensures the database directory and SQLite file exists', function (): void {
        // Arrange, teardown the directory created by the setup hook
        File::deleteDirectory(dirname($this->storagePath));
        $dbPath = $this->storagePath.'/artisense.sqlite';

        // Act
        $this->repositoryManager->newConnection();

        // Assert
        expect(File::isDirectory(dirname($dbPath)))->toBeTrue();
        expect(File::exists($dbPath))->toBeTrue();
    });

    it('configures the connection with the correct settings', function (): void {
        // Act
        $this->repositoryManager->newConnection();

        // Assert
        $connectionConfig = Config::get('database.connections.artisense');
        expect($connectionConfig)->toBeArray()
            ->and($connectionConfig['driver'])->toBe('sqlite')
            ->and($connectionConfig['prefix'])->toBe('');
    });
});
