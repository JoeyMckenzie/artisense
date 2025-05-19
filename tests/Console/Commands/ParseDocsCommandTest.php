<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\ParseDocsCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use PDO;
use Symfony\Component\Console\Command\Command;

covers(ParseDocsCommand::class);

describe(ParseDocsCommand::class, function (): void {
    beforeEach(function (): void {
        $this->files = new Filesystem();
        $this->storagePath = storage_path('/artisense');
        $this->files->deleteDirectory($this->storagePath);
        expect($this->files->exists($this->storagePath))->toBeFalse();

        // Create test docs directory and copy test fixture
        $this->files->makeDirectory($this->storagePath.'/docs', 0755, true);
        $this->files->copy(
            __DIR__.'/../../Fixtures/artisan.md',
            $this->storagePath.'/docs/artisan.md'
        );

        // Set base URL for links
        Config::set('artisense.base_url', 'https://laravel.com/docs');
    });

    afterEach(function (): void {
        $this->files->deleteDirectory($this->storagePath);
    });

    it('parses markdown docs and stores them in the database', function (): void {
        // Arrange
        expect($this->files->exists($this->storagePath.'/artisense.sqlite'))->toBeFalse();

        // Act
        $this->artisan(ParseDocsCommand::class)
            ->expectsOutput('🔍 Parsing Laravel docs...')
            ->expectsOutput('Preparing database...')
            ->expectsOutput('Found 1 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        // Assert
        expect($this->files->exists($this->storagePath.'/artisense.sqlite'))->toBeTrue();

        // Connect to the database and verify content was stored
        $db = new PDO('sqlite:'.$this->storagePath.'/artisense.sqlite');
        $result = $db->query('SELECT COUNT(*) FROM docs')->fetchColumn();
        expect((int) $result)->toBeGreaterThan(0);

        // Verify specific content was parsed correctly
        $statement = $db->prepare('SELECT * FROM docs WHERE heading = ?');
        $statement->execute(['Artisan Console']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        expect($row)->not->toBeNull();
        expect($row['title'])->toBe('Artisan Console');
        expect($row['link'])->toContain('https://laravel.com/docs/artisan#artisan-console');
    });

    it('handles files without headings', function (): void {
        // Arrange
        $this->files->put(
            $this->storagePath.'/docs/no-headings.md',
            'This is a test file with no headings.'
        );

        // Act
        $this->artisan(ParseDocsCommand::class)
            ->expectsOutput('🔍 Parsing Laravel docs...')
            ->expectsOutput('Preparing database...')
            ->expectsOutput('Found 2 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $db = new PDO('sqlite:'.$this->storagePath.'/artisense.sqlite');
        $statement = $db->prepare('SELECT * FROM docs WHERE heading = ?');
        $statement->execute(['[Intro]']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        expect($row)->not->toBeNull();
        expect($row['title'])->toBe('[Untitled]');
        expect($row['markdown'])->toContain('This is a test file with no headings.');
    });

    it('creates database tables correctly', function (): void {
        // Act
        $this->artisan(ParseDocsCommand::class)
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $db = new PDO('sqlite:'.$this->storagePath.'/artisense.sqlite');

        // Check if the docs table exists and has the expected columns
        $result = $db->query('PRAGMA table_info(docs)')->fetchAll(PDO::FETCH_ASSOC);
        $columns = array_column($result, 'name');

        expect($columns)->toContain('title');
        expect($columns)->toContain('heading');
        expect($columns)->toContain('markdown');
        expect($columns)->toContain('content');
        expect($columns)->toContain('path');
        expect($columns)->toContain('link');
    });

    it('processes multiple headings in a single file', function (): void {
        // Act
        $this->artisan(ParseDocsCommand::class)
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $db = new PDO('sqlite:'.$this->storagePath.'/artisense.sqlite');

        // Check for multiple headings from the artisan.md file
        $headings = [
            'Artisan Console',
            'Introduction',
            'Tinker (REPL)',
            'Writing Commands',
        ];

        foreach ($headings as $heading) {
            $statement = $db->prepare('SELECT * FROM docs WHERE heading = ?');
            $statement->execute([$heading]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            expect($row)->not->toBeNull();
            expect($row['heading'])->toBe($heading);
        }
    });

    it('creates correct links with slugified headings', function (): void {
        // Act
        $this->artisan(ParseDocsCommand::class)
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $db = new PDO('sqlite:'.$this->storagePath.'/artisense.sqlite');

        // Check for a heading with special characters
        $statement = $db->prepare('SELECT * FROM docs WHERE heading = ?');
        $statement->execute(['Tinker (REPL)']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        expect($row)->not->toBeNull();
        expect($row['link'])->toContain('artisan#tinker-repl');
    });

    it('skips non-markdown files', function (): void {
        // Arrange
        $this->files->put(
            $this->storagePath.'/docs/not-markdown.txt',
            'This is not a markdown file.'
        );

        // Act
        $this->artisan(ParseDocsCommand::class)
            ->expectsOutput('🔍 Parsing Laravel docs...')
            ->expectsOutput('Preparing database...')
            ->expectsOutput('Found 2 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $db = new PDO('sqlite:'.$this->storagePath.'/artisense.sqlite');
        $statement = $db->prepare('SELECT COUNT(*) FROM docs WHERE markdown LIKE ?');
        $statement->execute(['%This is not a markdown file.%']);
        $count = (int) $statement->fetchColumn();

        expect($count)->toBe(0);
    });

    it('handles empty docs directory', function (): void {
        // Arrange
        $this->files->deleteDirectory($this->storagePath.'/docs');
        $this->files->makeDirectory($this->storagePath.'/docs', 0755, true);

        // Act
        $this->artisan(ParseDocsCommand::class)
            ->expectsOutput('🔍 Parsing Laravel docs...')
            ->expectsOutput('Preparing database...')
            ->expectsOutput('Found 0 docs files...')
            ->expectsOutput('✅ Docs parsed and stored!')
            ->assertExitCode(Command::SUCCESS);

        // Assert
        expect($this->files->exists($this->storagePath.'/artisense.sqlite'))->toBeTrue();

        $db = new PDO('sqlite:'.$this->storagePath.'/artisense.sqlite');
        $count = (int) $db->query('SELECT COUNT(*) FROM docs')->fetchColumn();
        expect($count)->toBe(0);
    });

    it('extracts and stores content without HTML tags', function (): void {
        // Arrange
        $markdownWithHtml = "# Test Heading\n\nThis is a <strong>test</strong> with <em>HTML</em> tags.";
        $this->files->put($this->storagePath.'/docs/html-test.md', $markdownWithHtml);

        // Act
        $this->artisan(ParseDocsCommand::class)
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $db = new PDO('sqlite:'.$this->storagePath.'/artisense.sqlite');
        $statement = $db->prepare('SELECT * FROM docs WHERE heading = ?');
        $statement->execute(['Test Heading']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        expect($row)->not->toBeNull();
        expect($row['markdown'])->toContain('<strong>test</strong>');
        expect($row['content'])->not->toContain('<strong>');
        expect($row['content'])->toContain('test');
    });

    it('handles different heading levels correctly', function (): void {
        // Arrange
        $markdownWithHeadings = "# H1 Heading\n\nContent 1\n\n## H2 Heading\n\nContent 2\n\n### H3 Heading\n\nContent 3";
        $this->files->put($this->storagePath.'/docs/headings-test.md', $markdownWithHeadings);

        // Act
        $this->artisan(ParseDocsCommand::class)
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $db = new PDO('sqlite:'.$this->storagePath.'/artisense.sqlite');

        // Check all heading levels were processed
        $headings = ['H1 Heading', 'H2 Heading', 'H3 Heading'];
        foreach ($headings as $heading) {
            $statement = $db->prepare('SELECT * FROM docs WHERE heading = ?');
            $statement->execute([$heading]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            expect($row)->not->toBeNull();
            expect($row['heading'])->toBe($heading);
        }

        // Check that H1 is used as title for all sections
        $statement = $db->prepare('SELECT * FROM docs WHERE heading = ?');
        $statement->execute(['H2 Heading']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        expect($row['title'])->toBe('H1 Heading');
    });
});
