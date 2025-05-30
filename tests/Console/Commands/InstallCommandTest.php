<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\InstallCommand;
use Artisense\Contracts\Actions\DownloadDocsActionContract;
use Artisense\Contracts\Actions\SeedDocsActionContract;
use Artisense\Exceptions\ArtisenseException;
use Illuminate\Support\Facades\App;
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
            ->expectsQuestion('Which version of documentation would you like to install?', [$this->version->value])
            ->expectsOutput('🔧 Installing artisense...')
            ->expectsOutput("Installing version {$this->version->value}...")
            ->expectsOutput('Downloading documentation...')
            ->expectsOutput('Storing documentation...')
            ->expectsOutput('✅  Artisense is ready!')
            ->assertExitCode(Command::SUCCESS);
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
            ->expectsQuestion('Which version of documentation would you like to install?', [$this->version->value])
            ->expectsOutput('🔧 Installing artisense...')
            ->expectsOutput("Installing version {$this->version->value}...")
            ->expectsOutput('Downloading documentation...')
            ->expectsOutputToContain('Download docs error.')
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
            ->expectsQuestion('Which version of documentation would you like to install?', [$this->version->value])
            ->expectsOutput("Installing version {$this->version->value}...")
            ->expectsOutput('Downloading documentation...')
            ->expectsOutput('Storing documentation...')
            ->expectsOutputToContain('Seed docs failed.')
            ->assertExitCode(Command::FAILURE);
    });
});
