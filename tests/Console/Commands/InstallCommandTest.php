<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Artisense;
use Artisense\Console\Commands\InstallCommand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command;

covers(InstallCommand::class);

describe(InstallCommand::class, function (): void {
    it('downloads and installs Laravel docs', function (): void {
        // Arrange
        $file = file_get_contents(__DIR__.'/../../Fixtures/laravel-docs.zip');
        Storage::fake('local');
        Http::fake([
            Artisense::GITHUB_SOURCE_ZIP => Http::response($file),
        ]);

        // Act
        $exitCode = $this->artisan('artisense:install')
            ->expectsOutput('🔧 Installing Artisense...')
            ->expectsOutput('Fetching Laravel docs from GitHub...')
            ->expectsOutput('Unzipping docs...')
            ->expectsOutput('✅ Laravel docs downloaded and ready in: storage/app/artisense/docs')
            ->assertExitCode(Command::SUCCESS);

        // Assert
        expect(Storage::disk('local')->exists('artisense/docs'))->toBeTrue()
            ->and($exitCode)->toBe(Command::SUCCESS);
    });
});
