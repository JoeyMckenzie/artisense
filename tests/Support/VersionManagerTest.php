<?php

declare(strict_types=1);

namespace Artisense\Tests\Support;

use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\DocumentationVersionException;
use Artisense\Support\VersionManager;
use Illuminate\Contracts\Config\Repository;
use Mockery;

covers(VersionManager::class);

describe(VersionManager::class, function (): void {
    beforeEach(function (): void {
        $this->config = Mockery::mock(Repository::class);
        $this->versionManager = new VersionManager($this->config);
    });

    it('returns the version when a valid DocumentationVersion instance is provided', function (): void {
        // Arrange
        $expectedVersion = DocumentationVersion::VERSION_12;
        $this->config->shouldReceive('get')
            ->with('artisense.version')
            ->once()
            ->andReturn($expectedVersion);

        // Act
        $result = $this->versionManager->getVersion();

        // Assert
        expect($result)->toBe($expectedVersion);
    });

    it('returns the version when a valid version string is provided', function (): void {
        // Arrange
        $versionString = '12.x';
        $expectedVersion = DocumentationVersion::VERSION_12;
        $this->config->shouldReceive('get')
            ->with('artisense.version')
            ->once()
            ->andReturn($versionString);

        // Act
        $result = $this->versionManager->getVersion();

        // Assert
        expect($result)->toBe($expectedVersion);
    });

    it('throws an exception when the version is null', function (): void {
        // Arrange
        $this->config->shouldReceive('get')
            ->with('artisense.version')
            ->once()
            ->andReturnNull();

        // Act & Assert
        expect(fn () => $this->versionManager->getVersion())
            ->toThrow(DocumentationVersionException::class, 'Documentation version must be configured in your config file.');
    });

    it('throws an exception when the version is not a string', function (): void {
        // Arrange
        $this->config->shouldReceive('get')
            ->with('artisense.version')
            ->once()
            ->andReturn(123);

        // Act & Assert
        expect(fn () => $this->versionManager->getVersion())
            ->toThrow(DocumentationVersionException::class, "Documentation version must be a valid version string (e.g., '12.x', '11.x', 'master', etc.).");
    });

    it('throws an exception when the version string is invalid', function (): void {
        // Arrange
        $this->config->shouldReceive('get')
            ->with('artisense.version')
            ->once()
            ->andReturn('invalid-version');

        // Act & Assert
        expect(fn () => $this->versionManager->getVersion())
            ->toThrow(DocumentationVersionException::class, "Documentation version must be a valid version string (e.g., '12.x', '11.x', 'master', etc.).");
    });
});
