<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\InstallCommand;
use Artisense\Contracts\Actions\DownloadDocsActionContract;
use Artisense\Contracts\Actions\SeedDocsActionContract;
use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\ArtisenseException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Mockery;
use Symfony\Component\Console\Command\Command;

covers(InstallCommand::class);

describe(InstallCommand::class, function (): void {
    beforeEach(function (): void {
        // Mock the DownloadDocsAction
        $this->downloadDocsMock = Mockery::mock(DownloadDocsActionContract::class);
        $this->downloadDocsMock
            ->shouldReceive('handle')
            ->andReturnNull();

        // Mock the SeedDocsAction
        $this->seedDocsMock = Mockery::mock(SeedDocsActionContract::class);
        $this->seedDocsMock
            ->shouldReceive('handle')
            ->andReturnNull();

        App::bind(DownloadDocsActionContract::class, fn () => $this->downloadDocsMock);
        App::bind(SeedDocsActionContract::class, fn () => $this->seedDocsMock);
    });

    it('successfully installs artisense', function (): void {
        // Arrange & Act & Assert
        $this->artisan(InstallCommand::class)
            ->expectsOutput('🔧 Installing artisense...')
            ->expectsOutput("️Using version {$this->version->value}...")
            ->expectsOutput('Downloading documentation...')
            ->expectsOutput('Storing documentation...')
            ->expectsOutput('✅  Artisense is ready!')
            ->assertExitCode(Command::SUCCESS);
    });

    it('installs artisense with a specific documentation version', function (): void {
        // Arrange
        expect(Config::string('artisense.version'))->toBe($this->version->value);
        $version = DocumentationVersion::VERSION_11;

        // Act & Assert
        $this->artisan(InstallCommand::class, ['--docVersion' => $version->value])
            ->expectsOutput('🔧 Installing artisense...')
            ->expectsOutput("️Using version $version->value...")
            ->expectsOutput('Downloading documentation...')
            ->expectsOutput('Storing documentation...')
            ->expectsOutput('✅  Artisense is ready!')
            ->assertExitCode(Command::SUCCESS);

        // Verify the version was changed
        expect(Config::string('artisense.version'))->toBe($version->value);
    });

    it('returns failure when an invalid documentation version is provided', function (): void {
        // Arrange
        $invalidVersion = 'invalid-version';
        $validVersions = implode(', ', DocumentationVersion::values());

        // Act & Assert
        $this->artisan(InstallCommand::class, ['--docVersion' => $invalidVersion])
            ->expectsOutput('🔧 Installing artisense...')
            ->expectsOutput(sprintf('Invalid version "%s" provided, please use one of the following: %s', $invalidVersion, $validVersions))
            ->assertExitCode(Command::FAILURE);
    });

    it('returns failure when version manager throws an exception', function (): void {
        // Arrange, remove the version from config to trigger the missing version exception
        Config::set('artisense.version');

        // Act & Assert
        $this->artisan(InstallCommand::class)
            ->expectsOutput('🔧 Installing artisense...')
            ->expectsOutput('Failed to get version: Documentation version must be configured in your config file.')
            ->assertExitCode(Command::FAILURE);
    });

    it('returns failure when download docs action throws an exception', function (): void {
        // Arrange
        $mock = Mockery::mock(DownloadDocsActionContract::class);
        $mock
            ->shouldReceive('handle')
            ->andThrows(new ArtisenseException('Download docs error.'));

        App::instance(DownloadDocsActionContract::class, $mock);

        // Act & Assert
        $this->artisan(InstallCommand::class)
            ->expectsOutput('🔧 Installing artisense...')
            ->expectsOutput("️Using version {$this->version->value}...")
            ->expectsOutput('Downloading documentation...')
            ->expectsOutputToContain('Failed to install: Download docs error.')
            ->assertExitCode(Command::FAILURE);
    });

    it('returns failure when seed docs action throws an exception', function (): void {
        // Arrange
        $mock = Mockery::mock(SeedDocsActionContract::class);
        $mock
            ->shouldReceive('handle')
            ->andThrows(new ArtisenseException('Seed docs failed.'));

        App::instance(SeedDocsActionContract::class, $mock);

        // Act & Assert
        $this->artisan(InstallCommand::class)
            ->expectsOutput('🔧 Installing artisense...')
            ->expectsOutput("️Using version {$this->version->value}...")
            ->expectsOutput('Downloading documentation...')
            ->expectsOutput('Storing documentation...')
            ->expectsOutputToContain('Failed to install: Seed docs failed.')
            ->assertExitCode(Command::FAILURE);
    });
});
