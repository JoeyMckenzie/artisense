<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\SeedDocsCommand;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command;

covers(SeedDocsCommand::class);

describe(SeedDocsCommand::class, function (): void {
    beforeEach(function (): void {
        File::ensureDirectoryExists($this->storagePath.'/docs');
        File::copy(__DIR__.'/../../Fixtures/artisan.md', $this->storagePath.'/docs/artisan.md');
    });

    it('parses markdown docs and stores them in the database', function (): void {
        // Arrange & Act
        $this->artisan(SeedDocsCommand::class)
            ->expectsOutput('🔍 Preparing database...')
            ->expectsOutput('Found 1 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        // Connect to the database and verify content was stored
        $result = $this->db->get();
        expect(count($result))->toBeGreaterThan(0);

        // Verify specific content was parsed correctly
        $row = $this->db
            ->where(['heading' => 'Artisan Console'])
            ->get(['title', 'version', 'link'])
            ->first();
        expect($row)->not->toBeNull()
            ->and($row->title)->toBe('Artisan Console')
            ->and($row->version)->toBe($this->version->value)
            ->and($row->link)->toContain('https://laravel.com/docs/12.x/artisan#artisan-console');
    });

    it('handles files without headings', function (): void {
        // Arrange
        File::put(
            $this->storagePath.'/docs/no-headings.md',
            'This is a test file with no headings.'
        );

        // Act
        $this->artisan(SeedDocsCommand::class)
            ->expectsOutput('🔍 Preparing database...')
            ->expectsOutput('Found 2 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $row = $this->db
            ->where(['heading' => '[Intro]'])
            ->get(['title', 'markdown'])
            ->first();
        expect($row)->not->toBeNull()
            ->and($row->title)->toBe('[Untitled]')
            ->and($row->markdown)->toContain('This is a test file with no headings.');
    });

    it('creates database tables correctly', function (): void {
        // Arrange & Act
        $this->artisan(SeedDocsCommand::class)
            ->assertExitCode(Command::SUCCESS);

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
            ->and($result)->toContain('path')
            ->and($result)->toContain('link');
    });

    it('processes multiple headings in a single file', function (): void {
        // Act
        $this->artisan(SeedDocsCommand::class)
            ->assertExitCode(Command::SUCCESS);

        // Check for multiple headings from the artisan.md file
        $headings = [
            'Artisan Console',
            'Introduction',
            'Tinker (REPL)',
            'Writing Commands',
        ];

        $rows = $this->db
            ->whereIn('heading', $headings)
            ->get(['heading']);
        expect($rows)->not->toBeNull()
            ->and($rows->count())->toBe(count($headings))
            ->and($rows->pluck('heading')->toArray())->toBe($headings);
    });

    it('creates correct links with slugified headings', function (): void {
        // Arrange
        $this->artisan(SeedDocsCommand::class)
            ->assertExitCode(Command::SUCCESS);

        // Act
        $row = $this->db
            ->where(['heading' => 'Tinker (REPL)'])
            ->get(['link'])
            ->first();

        // Assert
        expect($row)->not->toBeNull()
            ->and($row->link)->toContain('artisan#tinker-repl');
    });

    it('skips non-markdown files', function (): void {
        // Arrange
        File::put(
            $this->storagePath.'/docs/not-markdown.txt',
            'This is not a markdown file.'
        );

        // Act
        $this->artisan(SeedDocsCommand::class)
            ->expectsOutput('🔍 Preparing database...')
            ->expectsOutput('Found 1 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $result = $this->db
            ->whereLike('markdown', '%This is not a markdown file.%')
            ->doesntExist();

        expect($result)->toBeTrue();
    });

    it('handles empty docs directory', function (): void {
        // Arrange
        File::deleteDirectory($this->storagePath.'/docs');
        File::ensureDirectoryExists($this->storagePath.'/docs');

        // Act
        $this->artisan(SeedDocsCommand::class)
            ->expectsOutput('🔍 Preparing database...')
            ->expectsOutput('Found 0 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $result = $this->db->get();
        expect(File::exists($this->storagePath.'/artisense.sqlite'))->toBeTrue();
        expect(count($result))->toBe(0);
    });

    it('extracts and stores content without HTML tags', function (): void {
        // Arrange
        $markdownWithHtml = "# Test Heading\n\nThis is a <strong>test</strong> with <em>HTML</em> tags.";
        File::put($this->storagePath.'/docs/html-test.md', $markdownWithHtml);

        // Act
        $this->artisan(SeedDocsCommand::class)
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $row = $this->db
            ->where(['heading' => 'Test Heading'])
            ->get(['markdown', 'content'])
            ->first();

        expect($row)->not->toBeNull()
            ->and($row->markdown)->toContain('<strong>test</strong>')
            ->and($row->content)->not->toContain('<strong>')
            ->and($row->content)->toContain('test');
    });

    it('handles different heading levels correctly', closure: function (): void {
        // Arrange
        $markdownWithHeadings = "# H1 Heading\n\nContent 1\n\n## H2 Heading\n\nContent 2\n\n### H3 Heading\n\nContent 3";
        File::put($this->storagePath.'/docs/headings-test.md', $markdownWithHeadings);

        // Act
        $this->artisan(SeedDocsCommand::class)
            ->assertExitCode(Command::SUCCESS);

        // Assert, check all heading levels were processed
        $headings = ['H1 Heading', 'H2 Heading', 'H3 Heading'];

        foreach ($headings as $heading) {
            $row = $this->db
                ->where(['heading' => $heading])
                ->get(['heading'])
                ->first();

            expect($row)->not->toBeNull()
                ->and($row->heading)->toBe($heading);
        }

        // Check that H1 is used as title for all sections
        $row = $this->db
            ->where(['heading' => 'H2 Heading'])
            ->get(['title'])
            ->first();
        expect($row->title)->toBe('H1 Heading');
    });
});
