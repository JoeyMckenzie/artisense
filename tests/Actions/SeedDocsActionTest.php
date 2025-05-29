<?php

declare(strict_types=1);

namespace Artisense\Tests\Actions;

use Artisense\Actions\SeedDocsAction;
use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\ArtisenseException;
use Artisense\Models\DocumentationEntry;
use Artisense\Support\DocumentationDatabaseManager;
use Artisense\Support\StorageManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

covers(SeedDocsAction::class);

describe(SeedDocsAction::class, function (): void {
    beforeEach(function (): void {
        $this->docsPath = $this->storagePath.'/'.$this->version->getExtractedFolderName();
        File::ensureDirectoryExists($this->docsPath);
        File::copy(__DIR__.'/../Fixtures/artisan.md', $this->docsPath.'/artisan.md');

        $dbManager = app(DocumentationDatabaseManager::class);
        $dbManager->initializeDatabase();

        $this->action = new SeedDocsAction(
            app(StorageManager::class),
            app(Filesystem::class),
        );
    });

    it('parses markdown docs and stores them in the database', function (): void {
        // Arrange & Act
        $this->action->handle($this->version);

        // Assert
        $result = $this->db->get();
        expect(count($result))->toBeGreaterThan(0);

        // Verify specific content was parsed correctly
        $entry = DocumentationEntry::query()
            ->where('heading', '=', 'Artisan Console')
            ->get(['title', 'version', 'link'])
            ->first();
        expect($entry)->not->toBeNull()
            ->and($entry->title)->toBe('Artisan Console')
            ->and($entry->version)->toBe($this->version->value)
            ->and($entry->link)->toContain('artisan#artisan-console');
    });

    it('removes existing entries by version before seeding new docs', function (): void {
        // Arrange, run the action to seed the DB
        $this->action->handle($this->version);

        $result = DocumentationEntry::all();
        expect(count($result))->toBe(45);

        // Act, run the action again
        $this->action->handle($this->version);

        // Assert, count should still be the same due to deleting => re-seeding process
        $result = DocumentationEntry::all();
        expect(count($result))->toBe(45);
    });

    it('handles files without headings', function (): void {
        // Arrange
        File::put(
            $this->docsPath.'/no-headings.md',
            'This is a test file with no headings.'
        );

        // Act
        $this->action->handle($this->version);

        // Assert
        $entry = DocumentationEntry::query()
            ->where('heading', '=', '[Intro]')
            ->get(['title', 'markdown'])
            ->first();
        expect($entry)->not->toBeNull()
            ->and($entry->title)->toBe('[Untitled]')
            ->and($entry->markdown)->toContain('This is a test file with no headings.');
    });

    it('creates database tables correctly', function (): void {
        // Arrange & Act
        $this->action->handle($this->version);

        // Assert
        $query = $this->connection
            ->select('PRAGMA table_info(docs)');
        $result = collect($query)
            ->pluck('name')
            ->toArray();

        expect($result)->toContain('title')
            ->and($result)->toContain('heading')
            ->and($result)->toContain('markdown')
            ->and($result)->toContain('content')
            ->and($result)->toContain('embedding')
            ->and($result)->toContain('path')
            ->and($result)->toContain('link');
    });

    it('processes multiple headings in a single file', function (): void {
        // Act
        $this->action->handle($this->version);

        // Check for multiple headings from the artisan.md file
        $headings = [
            'Artisan Console',
            'Introduction',
            'Tinker (REPL)',
            'Writing Commands',
        ];

        $entries = DocumentationEntry::query()
            ->whereIn('heading', $headings)
            ->get(['heading']);
        expect($entries)->not->toBeNull()
            ->and($entries->count())->toBe(count($headings))
            ->and($entries->pluck('heading')->toArray())->toBe($headings);
    });

    it('creates correct links with slugified headings', function (): void {
        // Arrange
        $this->action->handle($this->version);

        // Act
        $entry = DocumentationEntry::query()
            ->where('heading', '=', 'Tinker (REPL)')
            ->get(['link'])
            ->first();

        // Assert
        expect($entry)->not->toBeNull()
            ->and($entry->link)->toContain('artisan#tinker-repl');
    });

    it('skips non-markdown files', function (): void {
        // Arrange
        File::put(
            $this->docsPath.'/not-markdown.txt',
            'This is not a markdown file.'
        );

        // Act
        $this->action->handle($this->version);

        // Assert
        $result = DocumentationEntry::query()
            ->whereLike('markdown', 'This is not a markdown file.')
            ->doesntExist();

        expect($result)->toBeTrue();
    });

    it('handles empty docs directory', function (): void {
        // Arrange
        File::deleteDirectory($this->docsPath);
        File::ensureDirectoryExists($this->docsPath);

        // Act & Assert
        expect(fn () => $this->action->handle($this->version))
            ->not->toThrow(ArtisenseException::class);

        // Assert
        $result = DocumentationEntry::all();
        expect(File::exists($this->storagePath.'/artisense.sqlite'))->toBeTrue();
        expect(count($result))->toBe(0);
    });

    it('extracts and stores content without HTML tags', function (): void {
        // Arrange
        $markdownWithHtml = "# Test Heading\n\nThis is a <strong>test</strong> with <em>HTML</em> tags.";
        File::put($this->docsPath.'/html-test.md', $markdownWithHtml);

        // Act
        $this->action->handle($this->version);

        // Assert
        $entry = DocumentationEntry::query()
            ->where('heading', '=', 'Test Heading')
            ->get(['markdown', 'content'])
            ->first();

        expect($entry)->not->toBeNull()
            ->and($entry->markdown)->toContain('<strong>test</strong>')
            ->and($entry->content)->not->toContain('<strong>')
            ->and($entry->content)->toContain('test');
    });

    it('handles different heading levels correctly', function (): void {
        // Arrange
        $markdownWithHeadings = "# H1 Heading\n\nContent 1\n\n## H2 Heading\n\nContent 2\n\n### H3 Heading\n\nContent 3";
        File::put($this->docsPath.'/headings-test.md', $markdownWithHeadings);

        // Act
        $this->action->handle($this->version);

        // Assert, check all heading levels were processed
        $headings = ['H1 Heading', 'H2 Heading', 'H3 Heading'];

        foreach ($headings as $heading) {
            $entry = DocumentationEntry::query()
                ->where('heading', '=', $heading)
                ->get(['heading'])
                ->first();

            expect($entry)->not->toBeNull()
                ->and($entry->heading)->toBe($heading);
        }

        // Check that H1 is used as title for all sections
        $entry = DocumentationEntry::query()
            ->where('heading', '=', 'H2 Heading')
            ->get(['title'])
            ->first();
        expect($entry->title)->toBe('H1 Heading');
    });

    it('handles different documentation versions', function (): void {
        // Arrange
        $version = DocumentationVersion::VERSION_11;
        $docsPath = $this->storagePath.'/'.$version->getExtractedFolderName();
        File::ensureDirectoryExists($docsPath);
        File::copy(__DIR__.'/../Fixtures/artisan-11.md', $docsPath.'/artisan.md');

        // Act
        $this->action->handle($version);

        // Assert
        $entry = DocumentationEntry::query()
            ->where('heading', '=', 'Artisan Console (11.x)')
            ->get(['title', 'version', 'link'])
            ->first();
        expect($entry)->not->toBeNull()
            ->and($entry->title)->toBe('Artisan Console (11.x)')
            ->and($entry->version)->toBe($version->value);
    });

    it('throws exception when docs directory does not exist', function (): void {
        // Arrange
        $version = DocumentationVersion::VERSION_11;
        $docsPath = $this->storagePath.'/'.$version->getExtractedFolderName();
        File::deleteDirectory($docsPath);

        // Act & Assert
        expect(fn () => $this->action->handle($version))
            ->toThrow(ArtisenseException::class, sprintf('Documentation for version "%s" does not exist.', $version->value));
    });
});
