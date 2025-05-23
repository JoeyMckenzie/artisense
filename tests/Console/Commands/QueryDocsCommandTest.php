<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\QueryDocsCommand;
use Artisense\Enums\DocumentationVersion;
use Artisense\Support\Services\VersionManager;
use Artisense\Tests\Fixtures\TestOutputFormatter;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Command\Command;

covers(QueryDocsCommand::class);

describe(QueryDocsCommand::class, function (): void {
    beforeEach(function (): void {
        // Create the docs table
        $this->connection->statement('DROP TABLE IF EXISTS docs');
        $this->connection->statement('CREATE VIRTUAL TABLE docs USING fts5(title, heading, markdown, content, path, version, link)');

        // Insert test data for Introduction
        $this->db->insert([
            'title' => 'Artisan Console',
            'heading' => 'Introduction',
            'markdown' => 'Artisan is the command-line interface included with Laravel.',
            'content' => 'Artisan is the command-line interface included with Laravel.',
            'path' => 'artisan.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/artisan#introduction',
        ]);

        // Insert test data for Writing Commands
        $this->db->insert([
            'title' => 'Artisan Console',
            'heading' => 'Writing Commands',
            'markdown' => 'In addition to the commands provided with Artisan, you may build your own custom commands.',
            'content' => 'In addition to the commands provided with Artisan, you may build your own custom commands.',
            'path' => 'artisan.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/artisan#writing-commands',
        ]);
    });

    it('returns search results when matches are found', function (): void {
        $this->artisan(QueryDocsCommand::class)
            ->expectsQuestion('What are you looking for?', 'artisan command')
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('Artisan is the command-line interface included with Laravel.')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/12.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays a message when no results are found', function (): void {
        // Act & Assert
        $this->artisan(QueryDocsCommand::class)
            ->expectsQuestion('What are you looking for?', 'reverb')
            ->expectsOutput('No results found for your query.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('handles multiple search results', function (): void {
        // Act & Assert
        $this->artisan(QueryDocsCommand::class)
            ->expectsQuestion('What are you looking for?', 'artisan')
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('Artisan Console - Writing Commands')
            ->expectsOutputToContain('In addition to the commands provided with Artisan, you may build your own custom commands.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('returns search results when using --query option', function (): void {
        // Act & Assert
        $this->artisan(QueryDocsCommand::class, ['--query' => 'artisan command'])
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('Artisan is the command-line interface included with Laravel.')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/12.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays a message when no results are found using --query option', function (): void {
        // Act & Assert
        $this->artisan(QueryDocsCommand::class, [
            '--query' => 'reverb',
            '--limit' => 2,
        ])
            ->expectsOutput('No results found for your query.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('handles multiple search results when using --query option', function (): void {
        // Act & Assert
        $this->artisan(QueryDocsCommand::class, ['--query' => 'artisan'])
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('Artisan Console - Writing Commands')
            ->expectsOutputToContain('In addition to the commands provided with Artisan, you may build your own custom commands.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('formats markdown with proper syntax highlighting', function (): void {
        // Arrange, insert test data with various markdown elements
        $markdown = <<<'MARKDOWN'
# Heading 1

## Heading 2

### Heading 3

**Bold text** and *italic text*

- List item 1
- List item 2

1. Numbered item 1
2. Numbered item 2

```php
echo 'Code block';
```
Inline `code` example

[Link text](https://example.com)",
MARKDOWN;

        $this->db->insert([
            'title' => 'Markdown Test',
            'heading' => 'Formatting',
            'markdown' => $markdown,
            'content' => 'Markdown formatting test',
            'path' => 'markdown-test.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/markdown-test',
        ]);

        // Act & Assert, we're not checking specific formatting here, just that it doesn't error
        // and that the content is still present, but h1 headings should be skipped
        $this->artisan(QueryDocsCommand::class, ['--query' => 'markdown formatting'])
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Markdown Test - Formatting')
            ->expectsOutputToContain($markdown)
            ->assertExitCode(Command::SUCCESS);
    });

    it('excludes h1 headings from search results', function (): void {
        // Arrange, insert test data with h1 heading (title equals heading)
        $this->connection->table('docs')->insert([
            'title' => 'H1 Heading',
            'heading' => 'H1 Heading', // This is an h1 heading (title equals heading)
            'markdown' => "# H1 Heading\n\nThis is content under an h1 heading.",
            'content' => 'H1 Heading This is content under an h1 heading.',
            'path' => 'h1-test.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/h1-test',
        ]);

        // Insert a regular section with h2 heading
        $this->connection->table('docs')->insert([
            'title' => 'Test Document',
            'heading' => 'H2 Section', // This is not an h1 heading (title doesn't equal heading)
            'markdown' => "## H2 Section\n\nThis is content under an h2 heading.",
            'content' => 'H2 Section This is content under an h2 heading.',
            'path' => 'h2-test.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/h2-test',
        ]);

        // Act & Assert - Verify that h1 headings are excluded from search results
        $this->artisan(QueryDocsCommand::class, ['--query' => 'heading'])
            ->expectsOutput('🔍 Found relevant information:')
            ->doesntExpectOutputToContain('H1 Heading') // h1 heading should be excluded
            ->expectsOutputToContain('Test Document - H2 Section') // h2 heading should be included
            ->expectsOutputToContain('This is content under an h2 heading.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('formats output using a custom formatter when configured', function (): void {
        // Arrange
        Config::set('artisense.formatter', TestOutputFormatter::class);

        // Act & Assert
        $this->artisan(QueryDocsCommand::class, ['--query' => 'artisan command'])
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('FORMATTED: Artisan is the command-line interface included with Laravel.')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/12.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);
    });

    it('falls back to raw markdown when no formatter is configured', function (): void {
        // Arrange - Ensure no formatter is configured
        Config::set('artisense.formatter', null);

        // Act & Assert
        $this->artisan(QueryDocsCommand::class, ['--query' => 'artisan command'])
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('Artisan is the command-line interface included with Laravel.')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/12.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);
    });

    it('falls back to basic formatting when formatter class is invalid', function (): void {
        // Arrange - Set an invalid formatter class
        Config::set('artisense.formatter', 'NonExistentFormatter');

        // Act & Assert
        $this->artisan(QueryDocsCommand::class, ['--query' => 'artisan command'])
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('Failed to format markdown with the configured formatter, using basic formatting.')
            ->expectsOutputToContain('Artisan is the command-line interface included with Laravel.')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/12.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);
    });

    it('allows for searching specific version if flag is passed', function (): void {
        // Arrange
        expect(app(VersionManager::class)->getVersion())->toBe(DocumentationVersion::VERSION_12);

        $this->db->insert([
            'title' => 'Artisan Console (11.x)',
            'heading' => 'Introduction (11.x)',
            'markdown' => 'Artisan is the command-line interface included with Laravel. (11.x)',
            'content' => 'Artisan is the command-line interface included with Laravel. (11.x)',
            'path' => 'artisan.md',
            'version' => DocumentationVersion::VERSION_11->value,
            'link' => 'https://laravel.com/docs/11.x/artisan#introduction',
        ]);

        // Act & Assert
        $this->artisan(QueryDocsCommand::class, ['--docVersion' => '11.x'])
            ->expectsQuestion('What are you looking for?', 'artisan command')
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console (11.x) - Introduction (11.x)')
            ->expectsOutputToContain('Artisan is the command-line interface included with Laravel. (11.x)')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/11.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);

        expect(app(VersionManager::class)->getVersion())->toBe(DocumentationVersion::VERSION_11);
    });
});
