<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\DownloadDocsCommand;
use Artisense\Enums\DocumentationVersion;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Command\Command;

covers(DownloadDocsCommand::class);

describe(DownloadDocsCommand::class, function (): void {
    beforeEach(function (): void {
        Http::preventStrayRequests();
        $this->files = new Files();
        $this->storagePath = storage_path('/artisense');
        $this->files->deleteDirectory($this->storagePath);
        expect($this->files->exists($this->storagePath))->toBeFalse();
    });

    afterEach(function (): void {
        $this->files->deleteDirectory($this->storagePath);
    });

    it('downloads and installs Laravel docs, returning successful code', function (): void {
        // Arrange
        $zipPath = __DIR__.'/../../Fixtures/docs-12.x.zip';
        $zipContent = file_get_contents($zipPath);
        $version = DocumentationVersion::VERSION_12;

        Http::fake([
            $version->getZipUrl() => Http::response($zipContent),
        ]);

        // Act & assert
        $this->artisan(DownloadDocsCommand::class)
            ->expectsOutput('🔧 Downloading documents...')
            ->expectsOutput('Using version 12.x, fetching Laravel docs from GitHub...')
            ->doesntExpectOutput('Failed to download docs from GitHub.')
            ->doesntExpectOutputToContain('Failed to unzip docs: ')
            ->expectsOutput('Unzipping docs...')
            ->expectsOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::SUCCESS);

        expect($this->files->exists($this->storagePath.'/docs'))->toBeTrue()
            ->and($this->files->isEmptyDirectory($this->storagePath.'/docs'))->toBeFalse()
            ->and($this->files->exists($this->storagePath.'/laravel-docs.zip'))->toBeFalse()
            ->and($this->files->exists($this->storagePath.'/docs-12.x'))->toBeFalse();
    });

    it('returns failure code if HTTP retrieval fails', function (): void {
        // Arrange
        $version = DocumentationVersion::VERSION_12;

        Http::fake([
            $version->getZipUrl() => Http::response(null, 500),
        ]);

        // Act & assert
        $this->artisan(DownloadDocsCommand::class)
            ->expectsOutput('🔧 Downloading documents...')
            ->expectsOutput('Using version 12.x, fetching Laravel docs from GitHub...')
            ->expectsOutput('Failed to download docs from GitHub.')
            ->doesntExpectOutputToContain('Failed to unzip docs: ')
            ->doesntExpectOutput('Unzipping docs...')
            ->doesntExpectOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::FAILURE);

        expect($this->files->exists($this->storagePath))->toBeFalse();
    });

    it('downloads and installs docs with a different version', function (): void {
        // Arrange
        $zipPath = __DIR__.'/../../Fixtures/docs-master.zip';
        $zipContent = file_get_contents($zipPath);
        $version = DocumentationVersion::MASTER;

        Http::fake([
            $version->getZipUrl() => Http::response($zipContent),
        ]);

        // Mock the config to return MASTER version
        Config::set('artisense.version', DocumentationVersion::MASTER);

        // Act & assert
        $this->artisan(DownloadDocsCommand::class)
            ->expectsOutput('🔧 Downloading documents...')
            ->expectsOutput('Using version master, fetching Laravel docs from GitHub...')
            ->doesntExpectOutput('Failed to download docs from GitHub.')
            ->expectsOutput('Unzipping docs...')
            ->expectsOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::SUCCESS);

        expect($this->files->exists($this->storagePath.'/docs'))->toBeTrue()
            ->and($this->files->isEmptyDirectory($this->storagePath.'/docs'))->toBeFalse()
            ->and($this->files->exists($this->storagePath.'/laravel-docs.zip'))->toBeFalse()
            ->and($this->files->exists($this->storagePath.'/docs-master'))->toBeFalse();
    });

    it('handles pre-existing storage directory', function (): void {
        // Arrange
        $zipPath = __DIR__.'/../../Fixtures/docs-12.x.zip';
        $zipContent = file_get_contents($zipPath);
        $version = DocumentationVersion::VERSION_12;

        // Create the storage directory before running the command
        $this->files->makeDirectory($this->storagePath, 0755, true);
        $this->files->makeDirectory($this->storagePath.'/docs', 0755, true);
        expect($this->files->exists($this->storagePath))->toBeTrue();

        Http::fake([
            $version->getZipUrl() => Http::response($zipContent),
        ]);

        // Act & assert
        $this->artisan(DownloadDocsCommand::class)
            ->expectsOutput('🔧 Downloading documents...')
            ->expectsOutput('Using version 12.x, fetching Laravel docs from GitHub...')
            ->doesntExpectOutput('Failed to download docs from GitHub.')
            ->expectsOutput('Unzipping docs...')
            ->expectsOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::SUCCESS);

        expect($this->files->exists($this->storagePath.'/docs'))->toBeTrue()
            ->and($this->files->isEmptyDirectory($this->storagePath.'/docs'))->toBeFalse()
            ->and($this->files->exists($this->storagePath.'/laravel-docs.zip'))->toBeFalse();
    });

    it('handles ZIP extraction failure', function (): void {
        // Arrange
        $version = DocumentationVersion::VERSION_12;
        $invalidZipContent = 'This is not a valid ZIP file content';

        Http::fake([
            $version->getZipUrl() => Http::response($invalidZipContent),
        ]);

        // Act & assert
        $this->artisan(DownloadDocsCommand::class)
            ->expectsOutput('🔧 Downloading documents...')
            ->expectsOutput('Using version 12.x, fetching Laravel docs from GitHub...')
            ->doesntExpectOutput('Failed to download docs from GitHub.')
            ->expectsOutput('Unzipping docs...')
            ->expectsOutput('Failed to unzip docs.')
            ->doesntExpectOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::FAILURE);

        // The storage directory should exist with the zip file
        expect($this->files->exists($this->storagePath))->toBeTrue()
            ->and($this->files->exists($this->storagePath.'/laravel-docs.zip'))->toBeTrue();
    });
});
