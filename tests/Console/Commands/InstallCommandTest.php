<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Artisense;
use Artisense\Console\Commands\InstallCommand;
use Artisense\Contracts\Actions\UnzipsDocsArchiveAction;
use Artisense\Contracts\Support\StorageManager;
use Artisense\Exceptions\FailedToUnzipArchiveException;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Support\Facades\Http;
use Mockery;
use Symfony\Component\Console\Command\Command;

covers(InstallCommand::class);

describe(InstallCommand::class, function (): void {
    beforeEach(function (): void {
        Http::preventStrayRequests();
    });

    it('downloads and installs Laravel docs, returning successful code', function (): void {
        // Arrange, setup mocks
        $mockZipContent = 'fake-zip-content';
        $fakeZipPath = 'storage/artisense/laravel-docs.zip';
        $fakeExtractPath = 'storage/artisense';
        $mockFilesList = [
            'doc.md',
        ];

        // HTTP mock
        Http::fake([
            Artisense::GITHUB_SOURCE_ZIP => Http::response($mockZipContent),
        ]);

        // Filesystem mock
        $files = Mockery::mock(Files::class);
        $files->shouldReceive('move')
            ->withArgs(fn (string $source, string $target): bool => str_contains($source, 'doc.md') && str_contains($target, 'doc.md'));

        // Disk mock
        $disk = Mockery::mock(StorageManager::class);
        $disk->shouldReceive('ensureDirectoriesExist')->once();
        $disk->shouldReceive('put')->with('laravel-docs.zip', $mockZipContent)->once();
        $disk->shouldReceive('path')->with('laravel-docs.zip')->andReturn('storage/artisense/laravel-docs.zip');
        $disk->shouldReceive('getBasePath')->andReturn('storage/artisense');
        $disk->shouldReceive('files')->with('docs-master')->andReturn($mockFilesList);
        $disk->shouldReceive('path')->with('docs-master/doc.md')->andReturn('storage/artisense/docs-master/doc.md');
        $disk->shouldReceive('path')->with('docs/doc.md')->andReturn('storage/artisense/docs/doc.md');
        $disk->shouldReceive('delete')->with('laravel-docs.zip')->once();
        $disk->shouldReceive('deleteDirectory')->with('docs-master')->once();

        // Action mock
        $action = Mockery::mock(UnzipsDocsArchiveAction::class);
        $action->shouldReceive('handle')
            ->with($fakeZipPath, $fakeExtractPath)
            ->once();

        // Bind to container
        app()->instance(Files::class, $files);
        app()->instance(StorageManager::class, $disk);
        app()->instance(UnzipsDocsArchiveAction::class, $action);

        // Act & assert
        $this->artisan('artisense:install')
            ->expectsOutput('🔧 Installing Artisense...')
            ->expectsOutput('Fetching Laravel docs from GitHub...')
            ->doesntExpectOutput('Failed to download docs from GitHub.')
            ->doesntExpectOutputToContain('Failed to unzip docs: ')
            ->expectsOutput('Unzipping docs...')
            ->expectsOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::SUCCESS);
    });

    it('returns failure code if HTTP retrieval fals', function (): void {
        // Arrange, setup mocks
        Http::fake([
            Artisense::GITHUB_SOURCE_ZIP => Http::response(null, 500),
        ]);

        // Filesystem mock
        $files = Mockery::mock(Files::class);
        $files->shouldNotReceive('move');

        // Disk mock
        $disk = Mockery::mock(StorageManager::class);
        $disk->shouldNotReceive('ensureDirectoriesExist');
        $disk->shouldNotReceive('put');
        $disk->shouldNotReceive('path');
        $disk->shouldNotReceive('getBasePath');
        $disk->shouldNotReceive('files');
        $disk->shouldNotReceive('path');
        $disk->shouldNotReceive('path');
        $disk->shouldNotReceive('delete');
        $disk->shouldNotReceive('deleteDirectory');

        // Action mock
        $action = Mockery::mock(UnzipsDocsArchiveAction::class);
        $action->shouldNotReceive('handle');

        // Bind to container
        app()->instance(Files::class, $files);
        app()->instance(StorageManager::class, $disk);
        app()->instance(UnzipsDocsArchiveAction::class, $action);

        // Act & assert
        $this->artisan('artisense:install')
            ->expectsOutput('🔧 Installing Artisense...')
            ->expectsOutput('Fetching Laravel docs from GitHub...')
            ->expectsOutput('Failed to download docs from GitHub.')
            ->doesntExpectOutputToContain('Failed to unzip docs: ')
            ->doesntExpectOutput('Unzipping docs...')
            ->doesntExpectOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::FAILURE);
    });

    it('returns failure code if unzip action fails', function (): void {
        // Arrange, setup mocks
        $mockZipContent = 'fake-zip-content';
        $fakeZipPath = 'storage/artisense/laravel-docs.zip';
        $fakeExtractPath = 'storage/artisense';

        // HTTP mock
        Http::fake([
            Artisense::GITHUB_SOURCE_ZIP => Http::response($mockZipContent),
        ]);

        // Filesystem mock
        $files = Mockery::mock(Files::class);
        $files->shouldNotReceive('move');

        // Disk mock
        $disk = Mockery::mock(StorageManager::class);
        $disk->shouldReceive('ensureDirectoriesExist')->once();
        $disk->shouldReceive('put')->with('laravel-docs.zip', $mockZipContent)->once();
        $disk->shouldReceive('path')->with('laravel-docs.zip')->andReturn('storage/artisense/laravel-docs.zip');
        $disk->shouldReceive('getBasePath')->andReturn('storage/artisense');
        $disk->shouldNotReceive('files');
        $disk->shouldNotReceive('path');
        $disk->shouldNotReceive('path');
        $disk->shouldNotReceive('delete');
        $disk->shouldNotReceive('deleteDirectory');

        // Action mock
        $action = Mockery::mock(UnzipsDocsArchiveAction::class);
        $action->shouldReceive('handle')
            ->with($fakeZipPath, $fakeExtractPath)
            ->andThrow(new FailedToUnzipArchiveException());

        // Bind to container
        app()->instance(Files::class, $files);
        app()->instance(StorageManager::class, $disk);
        app()->instance(UnzipsDocsArchiveAction::class, $action);

        // Act & assert
        $this->artisan('artisense:install')
            ->expectsOutput('🔧 Installing Artisense...')
            ->expectsOutput('Fetching Laravel docs from GitHub...')
            ->doesntExpectOutput('Failed to download docs from GitHub.')
            ->expectsOutputToContain('Failed to unzip docs: ')
            ->expectsOutput('Unzipping docs...')
            ->doesntExpectOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::FAILURE);
    });
});
