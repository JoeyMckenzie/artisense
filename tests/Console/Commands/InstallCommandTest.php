<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Artisense;
use Artisense\Console\Commands\InstallCommand;
use Artisense\Contracts\Actions\UnzipsDocsArchiveAction;
use Artisense\Exceptions\FailedToUnzipArchiveException;
use Illuminate\Contracts\Filesystem\Filesystem as Disk;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Http;
use Mockery;
use Symfony\Component\Console\Command\Command;

covers(InstallCommand::class);

describe(InstallCommand::class, function (): void {
    it('downloads and installs Laravel docs, returning successful code', function (): void {
        // Arrange, setup mocks
        $mockZipContent = 'fake-zip-content';
        $fakeZipPath = 'storage/app/artisense/laravel-docs.zip';
        $fakeExtractPath = 'storage/app/artisense';
        $mockFilesList = [
            'artisense/docs-master/doc.md',
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
        $disk = Mockery::mock(Disk::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('makeDirectory')->times(2);
        $disk->shouldReceive('put')->with('artisense/laravel-docs.zip', $mockZipContent);
        $disk->shouldReceive('path')->andReturnUsing(fn (string $path): string => "storage/app/$path");
        $disk->shouldReceive('files')->with('artisense/docs-master')->andReturn($mockFilesList);
        $disk->shouldReceive('delete')->with('artisense/laravel-docs.zip');
        $disk->shouldReceive('deleteDirectory')->with('artisense/docs-master');

        // Storage mock
        $storage = Mockery::mock(FilesystemManager::class);
        $storage->shouldReceive('disk')->with('local')->andReturn($disk);

        // Action mock
        $action = Mockery::mock(UnzipsDocsArchiveAction::class);
        $action->shouldReceive('handle')->with($fakeZipPath, $fakeExtractPath);

        // Bind to container
        app()->instance(Files::class, $files);
        app()->instance(FilesystemManager::class, $storage);
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
        $disk = Mockery::mock(Disk::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('makeDirectory')->times(2);
        $disk->shouldNotReceive('put');
        $disk->shouldNotReceive('path');
        $disk->shouldNotReceive('files');
        $disk->shouldNotReceive('delete');
        $disk->shouldNotReceive('deleteDirectory');

        // Storage mock
        $storage = Mockery::mock(FilesystemManager::class);
        $storage->shouldReceive('disk')->with('local')->andReturn($disk);

        // Action mock
        $action = Mockery::mock(UnzipsDocsArchiveAction::class);
        $action->shouldNotReceive('handle');

        // Bind to container
        app()->instance(Files::class, $files);
        app()->instance(FilesystemManager::class, $storage);
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
        $fakeZipPath = 'storage/app/artisense/laravel-docs.zip';
        $fakeExtractPath = 'storage/app/artisense';

        // HTTP mock
        Http::fake([
            Artisense::GITHUB_SOURCE_ZIP => Http::response($mockZipContent),
        ]);

        // Filesystem mock
        $files = Mockery::mock(Files::class);
        $files->shouldNotReceive('move');

        // Disk mock
        $disk = Mockery::mock(Disk::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('makeDirectory')->times(2);
        $disk->shouldReceive('put')->with('artisense/laravel-docs.zip', $mockZipContent);
        $disk->shouldReceive('path')->andReturnUsing(fn (string $path): string => "storage/app/$path");
        $disk->shouldNotReceive('files');
        $disk->shouldNotReceive('delete');
        $disk->shouldNotReceive('deleteDirectory');

        // Storage mock
        $storage = Mockery::mock(FilesystemManager::class);
        $storage->shouldReceive('disk')->with('local')->andReturn($disk);

        // Action mock
        $action = Mockery::mock(UnzipsDocsArchiveAction::class);
        $action->shouldReceive('handle')
            ->with($fakeZipPath, $fakeExtractPath)
            ->andThrow(new FailedToUnzipArchiveException());

        // Bind to container
        app()->instance(Files::class, $files);
        app()->instance(FilesystemManager::class, $storage);
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
