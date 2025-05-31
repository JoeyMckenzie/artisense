<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\CleanupArtifactsCommand;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command;

covers(CleanupArtifactsCommand::class);

describe(CleanupArtifactsCommand::class, function (): void {
    beforeEach(function (): void {
        $this->zipsPath = sprintf('%s%s%s', $this->storagePath, DIRECTORY_SEPARATOR, 'zips');
        $this->docsPath = sprintf('%s%s%s', $this->storagePath, DIRECTORY_SEPARATOR, 'docs');

        // Simulate the docs and zips directories being created via actions
        File::ensureDirectoryExists($this->zipsPath);
        File::ensureDirectoryExists($this->docsPath);
    });

    it('cleanups up artifact files regardless of retention policy', function (bool $value): void {
        // Arrange
        Config::set('artisense.retain_artifacts', $value);

        // Act
        Config::set('artisense.retain_artifacts', false);
        $this->artisan(CleanupArtifactsCommand::class)
            ->expectsOutput('🗑️ Removing artifacts...')
            ->expectsOutput('✅  Artifacts removed!')
            ->assertExitCode(Command::SUCCESS);

        // Assert
        expect(File::isDirectory($this->zipsPath))->toBeFalse()
            ->and(File::isDirectory($this->docsPath))->toBeFalse();
    })->with([
        true,
        false,
    ]);
});
