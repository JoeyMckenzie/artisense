<?php

declare(strict_types=1);

namespace Artisense\Tests\Actions;

use Artisense\Actions\DownloadDocsAction;
use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\ArtisenseException;
use Artisense\Support\StorageManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http as HttpFacade;

covers(DownloadDocsAction::class);

describe(DownloadDocsAction::class, function (): void {
    beforeEach(function (): void {
        HttpFacade::preventStrayRequests();

        $this->action = new DownloadDocsAction(
            app(Http::class),
            app(StorageManager::class)
        );
    });

    it('downloads and extracts Laravel docs successfully', function (): void {
        // Arrange
        $zipPath = __DIR__.'/../Fixtures/docs-12.x.zip';
        $zipContent = file_get_contents($zipPath);

        HttpFacade::fake([
            $this->version->getZipUrl() => HttpFacade::response($zipContent),
        ]);

        // Act
        $this->action->handle($this->version);

        // Assert
        expect(File::exists($this->storagePath.'/docs-12.x'))->toBeTrue()
            ->and(File::isEmptyDirectory($this->storagePath.'/docs-12.x'))->toBeFalse()
            ->and(File::exists($this->storagePath.'/zips/laravel-docs-12.x.zip'))->toBeTrue();
    });

    it('throws exception when HTTP retrieval fails', function (): void {
        // Arrange
        HttpFacade::fake([
            $this->version->getZipUrl() => HttpFacade::response(null, 500),
        ]);

        // Act & Assert
        expect(fn () => $this->action->handle($this->version))
            ->toThrow(ArtisenseException::class, 'Failed to download docs from GitHub. Response code: 500')
            ->and(File::exists($this->storagePath.'/docs-12.x'))->toBeFalse()
            ->and(File::exists($this->storagePath.'/zips/laravel-docs-12.x.zip'))->toBeFalse();
    });

    it('handles different documentation versions', function (): void {
        // Arrange
        $version = DocumentationVersion::MASTER;
        $zipPath = __DIR__.'/../Fixtures/docs-master.zip';
        $zipContent = file_get_contents($zipPath);

        HttpFacade::fake([
            $version->getZipUrl() => HttpFacade::response($zipContent),
        ]);

        // Act
        $this->action->handle($version);

        // Assert
        expect(File::exists($this->storagePath.'/docs-master'))->toBeTrue()
            ->and(File::isEmptyDirectory($this->storagePath.'/docs-master'))->toBeFalse()
            ->and(File::exists($this->storagePath.'/zips/laravel-docs-master.zip'))->toBeTrue();
    });

    it('throws exception when ZIP extraction fails', function (): void {
        // Arrange
        $invalidZipContent = 'This is not a valid ZIP file content';

        HttpFacade::fake([
            $this->version->getZipUrl() => HttpFacade::response($invalidZipContent),
        ]);

        // Act & Assert
        expect(fn () => $this->action->handle($this->version))
            ->toThrow(ArtisenseException::class, 'Failed to unzip docs.')
            ->and(File::exists($this->storagePath.'/docs-12.x'))->toBeFalse()
            ->and(File::exists($this->storagePath.'/zips/laravel-docs-12.x.zip'))->toBeTrue();
    });

    it('handles network connection errors', function (): void {
        // Arrange
        HttpFacade::fake(function (): void {
            throw new ConnectionException('Connection failed');
        });

        // Act & Assert
        expect(fn () => $this->action->handle($this->version))
            ->toThrow(ConnectionException::class, 'Connection failed');
    });
});
