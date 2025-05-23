<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\DownloadDocsCommand;
use Artisense\Enums\DocumentationVersion;
use Artisense\Support\Services\VersionManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Command\Command;

covers(DownloadDocsCommand::class);

describe(DownloadDocsCommand::class, function (): void {
    beforeEach(function (): void {
        Http::preventStrayRequests();
    });

    it('downloads and installs Laravel docs, returning successful code', function (): void {
        // Arrange
        $zipPath = __DIR__.'/../../Fixtures/docs-12.x.zip';
        $zipContent = file_get_contents($zipPath);

        Http::fake([
            $this->version->getZipUrl() => Http::response($zipContent),
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

        expect(File::exists($this->storagePath.'/docs-12.x'))->toBeTrue()
            ->and(File::isEmptyDirectory($this->storagePath.'/docs-12.x'))->toBeFalse()
            ->and(File::exists($this->storagePath.'/laravel-docs.zip'))->toBeFalse();
    });

    it('returns failure code if HTTP retrieval fails', function (): void {
        // Arrange
        Http::fake([
            $this->version->getZipUrl() => Http::response(null, 500),
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
    });

    it('returns failure code if version is invalid', function (): void {
        // Arrange
        Config::set('artisense.version', 'invalid-version');

        // Act & assert
        $this->artisan(DownloadDocsCommand::class)
            ->expectsOutput('🔧 Downloading documents...')
            ->doesntExpectOutput('Using version 12.x, fetching Laravel docs from GitHub...')
            ->doesntExpectOutput('Failed to download docs from GitHub.')
            ->doesntExpectOutputToContain('Failed to unzip docs: ')
            ->doesntExpectOutput('Unzipping docs...')
            ->doesntExpectOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::FAILURE);
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
        Config::set('artisense.version', $version);

        // Act & assert
        $this->artisan(DownloadDocsCommand::class)
            ->expectsOutput('🔧 Downloading documents...')
            ->expectsOutput('Using version master, fetching Laravel docs from GitHub...')
            ->doesntExpectOutput('Failed to download docs from GitHub.')
            ->expectsOutput('Unzipping docs...')
            ->expectsOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::SUCCESS);

        expect(File::exists($this->storagePath.'/docs-master'))->toBeTrue()
            ->and(File::isEmptyDirectory($this->storagePath.'/docs-master'))->toBeFalse()
            ->and(File::exists($this->storagePath.'/laravel-docs.zip'))->toBeFalse();
    });

    it('handles pre-existing storage directory', function (): void {
        // Arrange
        $zipPath = __DIR__.'/../../Fixtures/docs-12.x.zip';
        $zipContent = file_get_contents($zipPath);

        // Create the storage directory before running the command
        File::ensureDirectoryExists($this->storagePath);
        File::ensureDirectoryExists($this->storagePath.'/docs-12.x');

        Http::fake([
            $this->version->getZipUrl() => Http::response($zipContent),
        ]);

        // Act & assert
        $this->artisan(DownloadDocsCommand::class)
            ->expectsOutput('🔧 Downloading documents...')
            ->expectsOutput('Using version 12.x, fetching Laravel docs from GitHub...')
            ->doesntExpectOutput('Failed to download docs from GitHub.')
            ->expectsOutput('Unzipping docs...')
            ->expectsOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::SUCCESS);

        expect(File::exists($this->storagePath.'/docs-12.x'))->toBeTrue()
            ->and(File::isEmptyDirectory($this->storagePath.'/docs-12.x'))->toBeFalse()
            ->and(File::exists($this->storagePath.'/laravel-docs.zip'))->toBeFalse();
    });

    it('handles ZIP extraction failure', function (): void {
        // Arrange
        $invalidZipContent = 'This is not a valid ZIP file content';

        Http::fake([
            $this->version->getZipUrl() => Http::response($invalidZipContent),
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
        expect(File::exists($this->storagePath))->toBeTrue()
            ->and(File::exists($this->storagePath.'/laravel-docs.zip'))->toBeTrue();
    });

    it('allows for versions to be specified as flags', function (): void {
        // Arrange
        expect(app(VersionManager::class)->getVersion())->toBe(DocumentationVersion::VERSION_12);

        $updatedVersion = DocumentationVersion::VERSION_11;
        $zipPath = __DIR__.'/../../Fixtures/docs-11.x.zip';
        $zipContent = file_get_contents($zipPath);

        Http::fake([
            $updatedVersion->getZipUrl() => Http::response($zipContent),
        ]);

        // Act & Assert
        $this->artisan(DownloadDocsCommand::class, ['--docVersion' => $updatedVersion->value])
            ->expectsOutput('🔧 Downloading documents...')
            ->expectsOutput('Using version 11.x, fetching Laravel docs from GitHub...')
            ->doesntExpectOutput('Failed to download docs from GitHub.')
            ->doesntExpectOutputToContain('Failed to unzip docs: ')
            ->expectsOutput('Unzipping docs...')
            ->expectsOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::SUCCESS);

        expect(app(VersionManager::class)->getVersion())->toBe(DocumentationVersion::VERSION_11) // Version should be overridden by flag
            ->and(File::exists($this->storagePath.'/docs-12.x'))->toBeFalse()
            ->and(File::exists($this->storagePath.'/docs-11.x'))->toBeTrue()
            ->and(File::isEmptyDirectory($this->storagePath.'/docs-11.x'))->toBeFalse()
            ->and(File::exists($this->storagePath.'/laravel-docs.zip'))->toBeFalse();
    });
});
