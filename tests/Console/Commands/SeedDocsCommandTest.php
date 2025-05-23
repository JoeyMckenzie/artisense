<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\SeedDocsCommand;
use Artisense\Enums\DocumentationVersion;
use Artisense\Support\Services\VersionManager;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command;

covers(SeedDocsCommand::class);

describe(SeedDocsCommand::class, function (): void {
    beforeEach(function (): void {
        $this->docsPath = $this->storagePath.'/'.$this->version->getExtractedFolderName();
        File::ensureDirectoryExists($this->docsPath);
        File::copy(__DIR__.'/../../Fixtures/artisan.md', $this->docsPath.'/artisan.md');
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

    it('removes existing entries by version before seeding new docs', function (): void {
        // Arrange, run the command to seed the DB
        $this->artisan(SeedDocsCommand::class)
            ->expectsOutput('🔍 Preparing database...')
            ->expectsOutput('Found 1 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        $result = $this->db->get();
        expect(count($result))->toBe(45);

        // Act, run the command again
        $this->artisan(SeedDocsCommand::class)
            ->expectsOutput('🔍 Preparing database...')
            ->expectsOutput('Found 1 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        // Assert, count should still be the same due to deleting => re-seeding process
        $result = $this->db->get();
        expect(count($result))->toBe(45);
    });

    it('handles files without headings', function (): void {
        // Arrange
        File::put(
            $this->docsPath.'/no-headings.md',
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
            $this->docsPath.'/not-markdown.txt',
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
        File::deleteDirectory($this->docsPath.'');
        File::ensureDirectoryExists($this->docsPath.'');

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
        File::put($this->docsPath.'/html-test.md', $markdownWithHtml);

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
        File::put($this->docsPath.'/headings-test.md', $markdownWithHeadings);

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

    it('allows seeding separate versions if a version flag is passed', function (): void {
        // Arrange
        expect(app(VersionManager::class)->getVersion())->toBe(DocumentationVersion::VERSION_12);

        // Simulate the docs previously being downloaded for a different version
        $docsPath = $this->storagePath.'/'.DocumentationVersion::VERSION_11->getExtractedFolderName();
        File::ensureDirectoryExists($docsPath);
        File::copy(__DIR__.'/../../Fixtures/artisan-11.md', $docsPath.'/artisan.md');

        $this->artisan(SeedDocsCommand::class, ['--docVersion' => DocumentationVersion::VERSION_11->value])
            ->expectsOutput('🔍 Preparing database...')
            ->expectsOutput('Found 1 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        // Connect to the database and verify content was stored
        $result = $this->db->get();
        expect(count($result))->toBeGreaterThan(0);

        // Verify specific content was parsed correctly
        $row = $this->db
            ->where(['heading' => 'Artisan Console (11.x)'])
            ->get(['title', 'version', 'link'])
            ->first();
        expect(app(VersionManager::class)->getVersion())->toBe(DocumentationVersion::VERSION_11)
            ->and($row)->not->toBeNull()
            ->and($row->title)->toBe('Artisan Console (11.x)')
            ->and($row->version)->toBe(DocumentationVersion::VERSION_11->value)
            ->and($row->link)->toContain('https://laravel.com/docs/11.x/artisan#artisan-console');
    });

    it('fails seeding separate versions if a version flag is passed and docs have not been downloaded', function (): void {
        // Arrange
        expect(app(VersionManager::class)->getVersion())->toBe(DocumentationVersion::VERSION_12);

        // Act & Assert
        $this->artisan(SeedDocsCommand::class, ['--docVersion' => DocumentationVersion::VERSION_11->value])
            ->expectsOutput('🔍 Preparing database...')
            ->expectsOutput('Documentation for version "11.x" does not exist, please first run the download command.')
            ->doesntExpectOutput('Found 1 docs files...')
            ->doesntExpectOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::FAILURE);
    });
});
