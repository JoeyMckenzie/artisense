<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\ParseDocsCommand;
use Artisense\Contracts\Support\StorageManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\SplFileInfo;

covers(ParseDocsCommand::class);

describe(ParseDocsCommand::class, function (): void {
    it('successfully parses and stores documentation in the database', function (): void {
        // Arrange
        $fakeDocsPath = 'storage/artisense/docs';
        $fakeDbPath = 'storage/artisense/artisense.sqlite';
        $fakeMarkdownContent = "# Test Heading\n\nThis is test content.\n\n## Second Heading\n\nMore test content.";

        // Mock StorageManager
        $disk = Mockery::mock(StorageManager::class);
        $disk->shouldReceive('path')->with('docs')->andReturn($fakeDocsPath);
        $disk->shouldReceive('path')->with('artisense.sqlite')->andReturn($fakeDbPath);

        // Mock Filesystem
        $files = Mockery::mock(Filesystem::class);
        $files->shouldReceive('ensureDirectoryExists')->once()->with(dirname($fakeDbPath));
        $files->shouldReceive('exists')->with($fakeDbPath)->andReturn(false);
        $files->shouldReceive('put')->with($fakeDbPath, '')->once();
        $files->shouldReceive('allFiles')->with($fakeDocsPath)->andReturn([
            new SplFileInfo(
                'test-doc.md',
                'docs',
                'docs/test-doc.md'
            ),
        ]);
        $files->shouldReceive('get')->andReturn($fakeMarkdownContent);

        // Mock Config Repository
        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('set')->withArgs(fn($arg): bool => isset($arg['database.connections.artisense']) &&
            $arg['database.connections.artisense']['driver'] === 'sqlite' &&
            $arg['database.connections.artisense']['database'] === $fakeDbPath);
        $config->shouldReceive('string')->with('artisense.base_url')->andReturn('https://laravel.com/docs/');

        // Mock Database Connection
        $db = Mockery::mock(ConnectionInterface::class);
        $db->shouldReceive('statement')->with('DROP TABLE IF EXISTS docs')->once();
        $db->shouldReceive('statement')->with('CREATE VIRTUAL TABLE docs USING fts5(title, heading, markdown, content, path, link)')->once();
        $db->shouldReceive('table')->with('docs')->andReturnSelf();
        $db->shouldReceive('insert')->withArgs(fn($arg): bool => isset($arg['title']) &&
            isset($arg['heading']) &&
            isset($arg['markdown']) &&
            isset($arg['content']) &&
            isset($arg['path']) &&
            isset($arg['link']))->times(2); // Once for each heading

        // Mock Connection Resolver
        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $resolver->shouldReceive('connection')->with('artisense')->andReturn($db);

        // Bind mocks to container
        app()->instance(StorageManager::class, $disk);
        app()->instance(Filesystem::class, $files);
        app()->instance(Repository::class, $config);
        app()->instance(ConnectionResolverInterface::class, $resolver);

        // Act & Assert
        $this->artisan('artisense:parse-docs')
            ->expectsOutput('🔍 Parsing Laravel docs...')
            ->expectsOutput('Preparing database...')
            ->expectsOutput('Found 1 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);
    });

    it('handles markdown files with no headings', function (): void {
        // Arrange
        $fakeDocsPath = 'storage/artisense/docs';
        $fakeDbPath = 'storage/artisense/artisense.sqlite';
        $fakeMarkdownContent = 'This is content without any headings.';

        // Mock StorageManager
        $disk = Mockery::mock(StorageManager::class);
        $disk->shouldReceive('path')->with('docs')->andReturn($fakeDocsPath);
        $disk->shouldReceive('path')->with('artisense.sqlite')->andReturn($fakeDbPath);

        // Mock Filesystem
        $files = Mockery::mock(Filesystem::class);
        $files->shouldReceive('ensureDirectoryExists')->once()->with(dirname($fakeDbPath));
        $files->shouldReceive('exists')->with($fakeDbPath)->andReturn(true);
        $files->shouldReceive('allFiles')->with($fakeDocsPath)->andReturn([
            new SplFileInfo(
                'no-headings.md',
                'docs',
                'docs/no-headings.md'
            ),
        ]);
        $files->shouldReceive('get')->andReturn($fakeMarkdownContent);

        // Mock Config Repository
        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('set')->withArgs(fn($arg): bool => isset($arg['database.connections.artisense']));
        $config->shouldReceive('string')->with('artisense.base_url')->andReturn('https://laravel.com/docs/');

        // Mock Database Connection
        $db = Mockery::mock(ConnectionInterface::class);
        $db->shouldReceive('statement')->with('DROP TABLE IF EXISTS docs')->once();
        $db->shouldReceive('statement')->with('CREATE VIRTUAL TABLE docs USING fts5(title, heading, markdown, content, path, link)')->once();
        $db->shouldReceive('table')->with('docs')->andReturnSelf();
        $db->shouldReceive('insert')->withArgs(fn($arg): bool => $arg['title'] === '[Untitled]' &&
            $arg['heading'] === '[Intro]')->once();

        // Mock Connection Resolver
        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $resolver->shouldReceive('connection')->with('artisense')->andReturn($db);

        // Bind mocks to container
        app()->instance(StorageManager::class, $disk);
        app()->instance(Filesystem::class, $files);
        app()->instance(Repository::class, $config);
        app()->instance(ConnectionResolverInterface::class, $resolver);

        // Act & Assert
        $this->artisan('artisense:parse-docs')
            ->expectsOutput('🔍 Parsing Laravel docs...')
            ->expectsOutput('Preparing database...')
            ->expectsOutput('Found 1 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);
    });

    it('skips non-markdown files', function (): void {
        // Arrange
        $fakeDocsPath = 'storage/artisense/docs';
        $fakeDbPath = 'storage/artisense/artisense.sqlite';

        // Mock StorageManager
        $disk = Mockery::mock(StorageManager::class);
        $disk->shouldReceive('path')->with('docs')->andReturn($fakeDocsPath);
        $disk->shouldReceive('path')->with('artisense.sqlite')->andReturn($fakeDbPath);

        // Mock Filesystem
        $files = Mockery::mock(Filesystem::class);
        $files->shouldReceive('ensureDirectoryExists')->once()->with(dirname($fakeDbPath));
        $files->shouldReceive('exists')->with($fakeDbPath)->andReturn(true);
        $files->shouldReceive('allFiles')->with($fakeDocsPath)->andReturn([
            new SplFileInfo(
                'image.png',
                'docs',
                'docs/image.png'
            ),
        ]);
        // Should not call get() for non-markdown files

        // Mock Config Repository
        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('set')->withArgs(fn($arg): bool => isset($arg['database.connections.artisense']));

        // Mock Database Connection
        $db = Mockery::mock(ConnectionInterface::class);
        $db->shouldReceive('statement')->with('DROP TABLE IF EXISTS docs')->once();
        $db->shouldReceive('statement')->with('CREATE VIRTUAL TABLE docs USING fts5(title, heading, markdown, content, path, link)')->once();
        // Should not call insert() for non-markdown files

        // Mock Connection Resolver
        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $resolver->shouldReceive('connection')->with('artisense')->andReturn($db);

        // Bind mocks to container
        app()->instance(StorageManager::class, $disk);
        app()->instance(Filesystem::class, $files);
        app()->instance(Repository::class, $config);
        app()->instance(ConnectionResolverInterface::class, $resolver);

        // Act & Assert
        $this->artisan('artisense:parse-docs')
            ->expectsOutput('🔍 Parsing Laravel docs...')
            ->expectsOutput('Preparing database...')
            ->expectsOutput('Found 1 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);
    });
});
