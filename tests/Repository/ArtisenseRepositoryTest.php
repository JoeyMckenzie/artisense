<?php

declare(strict_types=1);

namespace Artisense\Tests\Repository;

use Artisense\Enums\DocumentationVersion;
use Artisense\Repository\ArtisenseRepository;
use Artisense\Support\Services\VersionManager;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

covers(ArtisenseRepository::class);

describe(ArtisenseRepository::class, function (): void {
    beforeEach(function (): void {
        $this->versionManager = app(VersionManager::class);
        $this->repository = new ArtisenseRepository($this->connection, $this->versionManager);
    });

    it('creates the docs table correctly', function (): void {
        // Arrange & Act
        File::deleteDirectory($this->dbPath);
        $this->repository->createDocsTable();

        // Assert
        $tables = $this->connection->selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name='docs'");
        expect($tables)->not->toBeNull()
            ->and($tables->name)->toBe('docs');

        // Verify the table structure
        $tableInfo = $this->connection->select('PRAGMA table_info(docs)');
        $columns = collect($tableInfo)->pluck('name')->toArray();

        expect($columns)->toContain('title')
            ->and($columns)->toContain('heading')
            ->and($columns)->toContain('markdown')
            ->and($columns)->toContain('content')
            ->and($columns)->toContain('version')
            ->and($columns)->toContain('path')
            ->and($columns)->toContain('link');

        // Verify no errors if we recreate it again
        expect(fn () => $this->repository->createDocsTable())
            ->not->toThrow(Exception::class);
    });

    it('creates an entry in the docs table', function (): void {
        // Arrange
        $this->repository->createDocsTable();

        $title = 'Test Title';
        $heading = 'Test Heading';
        $markdown = '# Test Heading\n\nThis is test markdown.';
        $content = 'This is test content.';
        $path = 'test/path.md';
        $link = 'test-heading';

        // Act
        $this->repository->createEntry(
            $title,
            $heading,
            $markdown,
            $content,
            $path,
            $link
        );

        // Assert
        $entries = $this->db->get();
        expect($entries)->toHaveCount(1);

        $entry = $entries->first();
        expect($entry->title)->toBe($title)
            ->and($entry->heading)->toBe($heading)
            ->and($entry->markdown)->toBe($markdown)
            ->and($entry->content)->toBe($content)
            ->and($entry->version)->toBe($this->version->value)
            ->and($entry->path)->toBe($path)
            ->and($entry->link)->toBe('https://laravel.com/docs/12.x/test-heading');
    });

    it('creates entries with the correct base URL based on version', function (): void {
        // Arrange
        $this->repository->createDocsTable();

        // Change the version to 11.x
        Config::set('artisense.version', DocumentationVersion::VERSION_11);
        $repository = new ArtisenseRepository($this->connection, $this->versionManager);

        // Act
        $repository->createEntry(
            'Test Title',
            'Test Heading',
            'Test Markdown',
            'Test Content',
            'test/path.md',
            'test-link'
        );

        // Assert
        $entry = $this->db->first();
        expect($entry->link)->toBe('https://laravel.com/docs/11.x/test-link');
    });

    it('deletes entries for a given version', function (): void {
        // Arrange
        $this->repository->createDocsTable();

        // Seed some entries for 12.x
        $this->repository->createEntry(
            'Test Title 12.x',
            'Test Heading 12.x',
            'Test Markdown 12.x',
            'Test Content 12.x',
            'test/path.md 12.x',
            'test-link 12.x',
        );

        // Change the version to 11.x
        Config::set('artisense.version', DocumentationVersion::VERSION_11);
        $this->repository = new ArtisenseRepository($this->connection, $this->versionManager);

        $this->repository->createEntry(
            'Test Title 11.x',
            'Test Heading 11.x',
            'Test Markdown 11.x',
            'Test Content 11.x',
            'test/path.md 11.x',
            'test-link 11.x',
        );

        // Assert
        $entries = $this->db
            ->selectRaw('DISTINCT version')
            ->orderBy('version')
            ->get();
        expect($entries)->toHaveCount(2)
            ->and($entries->first()->version)->toBe('11.x')
            ->and($entries->last()->version)->toBe('12.x');

        // Act, delete rows for the current version (11.x)
        $this->repository->deleteExistingEntries();

        // Assert, 11.x should be removed
        $entries = $this->db
            ->selectRaw('DISTINCT version')
            ->get();
        expect($entries)->toHaveCount(1)
            ->and($entries->first()->version)->toBe('12.x');
    });
});
