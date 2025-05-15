<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Artisense;
use Artisense\Console\Commands\InstallCommand;
use Artisense\Contracts\Actions\UnzipsDocsArchiveAction;
use Illuminate\Contracts\Filesystem\Filesystem as Disk;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;
use Mockery;
use Symfony\Component\Console\Command\Command;

covers(InstallCommand::class);

describe(InstallCommand::class, function (): void {
    it('downloads and installs Laravel docs', function (): void {
        // Arrange
        $mockZipContent = 'fake-zip-content';
        $fakeZipPath = 'storage/app/artisense/laravel-docs.zip';
        $fakeExtractPath = 'storage/app/artisense';
        $mockFilesList = ['artisense/docs-master/README.md'];

        $content = file_get_contents(__DIR__.'/../../Fixtures/laravel-docs.zip');
        $response = Http::response($content, 200, ['Content-Type' => 'application/zip']);
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('get')
            ->with(Artisense::GITHUB_SOURCE_ZIP)
            ->andReturn($response);

        $files = Mockery::mock(Files::class);
        $files->shouldReceive('move')
            ->withArgs(fn($source, $target): bool => str_contains($source, 'README.md') && str_contains($target, 'README.md'));

        $disk = Mockery::mock(Disk::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('makeDirectory')->times(2);
        $disk->shouldReceive('put')->with('artisense/laravel-docs.zip', $mockZipContent);
        $disk->shouldReceive('path')->andReturnUsing(fn ($path): string => "storage/app/$path");
        $disk->shouldReceive('files')->with('artisense/docs-master')->andReturn($mockFilesList);
        $disk->shouldReceive('delete')->with('artisense/laravel-docs.zip');
        $disk->shouldReceive('deleteDirectory')->with('artisense/docs-master');

        $storage = Mockery::mock(FilesystemManager::class);
        $storage->shouldReceive('disk')->with('local')->andReturn($disk);

        $unzipper = Mockery::mock(UnzipsDocsArchiveAction::class);
        $unzipper->shouldReceive('handle')
            ->with($fakeZipPath, $fakeExtractPath);

        // Bind to container
        app()->instance(HttpClient::class, $http);
        app()->instance(Files::class, $files);
        app()->instance(FilesystemManager::class, $storage);
        app()->instance(UnzipsDocsArchiveAction::class, $unzipper);

        // Act
        $this->artisan('artisense:install')
            ->expectsOutput('🔧 Installing Artisense...')
            ->expectsOutput('Fetching Laravel docs from GitHub...')
            ->expectsOutput('Unzipping docs...')
            ->expectsOutput('✅ Laravel docs downloaded and ready!')
            ->assertExitCode(Command::SUCCESS);
    });
});
