<?php

declare(strict_types=1);

namespace Artisense\Tests\Support;

use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\DocumentationVersionException;
use Artisense\Support\Services\VersionManager;
use Illuminate\Contracts\Config\Repository;
use Mockery;

covers(VersionManager::class);

describe(VersionManager::class, function (): void {
    beforeEach(function (): void {
        $this->configMock = Mockery::mock(Repository::class);
        $this->versionManager = new VersionManager($this->configMock);
    });

    it('returns DocumentationVersion when config has enum value', function (): void {
        // Arrange
        $expectedVersion = DocumentationVersion::VERSION_12;
        $this->configMock->shouldReceive('get')
            ->with('artisense.version')
            ->once()
            ->andReturn($expectedVersion);

        // Act
        $result = $this->versionManager->getVersion();

        // Assert
        expect($result)->toBe($expectedVersion);
    });

    it('converts valid string to DocumentationVersion', function (): void {
        // Arrange
        $versionString = '11.x';
        $expectedVersion = DocumentationVersion::VERSION_11;
        $this->configMock->shouldReceive('get')
            ->with('artisense.version')
            ->once()
            ->andReturn($versionString);

        // Act
        $result = $this->versionManager->getVersion();

        // Assert
        expect($result)->toBe($expectedVersion);
    });

    it('throws exception when version is null', function (): void {
        // Arrange
        $this->configMock->shouldReceive('get')
            ->with('artisense.version')
            ->once()
            ->andReturnNull();

        // Act & Assert
        expect(fn () => $this->versionManager->getVersion())
            ->toThrow(DocumentationVersionException::class, 'Documentation version must be configured in your config file.');
    });

    it('throws exception when version is not a string or enum', function (): void {
        // Arrange
        $this->configMock->shouldReceive('get')
            ->with('artisense.version')
            ->once()
            ->andReturn(123);

        // Act & Assert
        expect(fn () => $this->versionManager->getVersion())
            ->toThrow(DocumentationVersionException::class, "Documentation version must be a valid version string (e.g., '12.x', '11.x', 'master', etc.).");
    });

    it('throws exception when version string is invalid', function (): void {
        // Arrange
        $this->configMock->shouldReceive('get')
            ->with('artisense.version')
            ->once()
            ->andReturn('invalid-version');

        // Act & Assert
        expect(fn () => $this->versionManager->getVersion())
            ->toThrow(DocumentationVersionException::class, "Documentation version must be a valid version string (e.g., '12.x', '11.x', 'master', etc.).");
    });
});
