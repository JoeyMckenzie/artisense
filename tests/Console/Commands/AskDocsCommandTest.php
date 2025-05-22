<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\AskDocsCommand;
use Symfony\Component\Console\Command\Command;

covers(AskDocsCommand::class);

describe(AskDocsCommand::class, function (): void {
    beforeEach(function (): void {
        // Create the docs table
        $this->connection->statement('DROP TABLE IF EXISTS docs');
        $this->connection->statement('CREATE VIRTUAL TABLE docs USING fts5(title, heading, markdown, content, path, version, link)');

        // Insert test data
        $this->db->insert([
            'title' => 'Artisan Console',
            'heading' => 'Introduction',
            'markdown' => 'Artisan is the command-line interface included with Laravel.',
            'content' => 'Artisan is the command-line interface included with Laravel.',
            'path' => 'artisan.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/artisan#introduction',
        ]);
    });

    it('returns search results when matches are found', function (): void {
        $this->artisan(AskDocsCommand::class)
            ->expectsQuestion('What are you looking for?', 'artisan command')
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('Artisan is the command-line interface included with Laravel.')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/12.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays a message when no results are found', function (): void {
        // Act & Assert
        $this->artisan(AskDocsCommand::class)
            ->expectsQuestion('What are you looking for?', 'reverb')
            ->expectsOutput('No results found for your query.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('handles multiple search results', function (): void {
        // Arrange, add another test entry
        $this->connection->table('docs')->insert([
            'title' => 'Artisan Console',
            'heading' => 'Writing Commands',
            'markdown' => 'In addition to the commands provided with Artisan, you may build your own custom commands.',
            'content' => 'In addition to the commands provided with Artisan, you may build your own custom commands.',
            'path' => 'artisan.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/artisan#writing-commands',
        ]);

        // Act & Assert
        $this->artisan(AskDocsCommand::class)
            ->expectsQuestion('What are you looking for?', 'artisan')
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('Artisan Console - Writing Commands')
            ->expectsOutputToContain('In addition to the commands provided with Artisan, you may build your own custom commands.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('returns search results when using --query option', function (): void {
        // Act & Assert
        $this->artisan(AskDocsCommand::class, ['--query' => 'artisan command'])
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('Artisan is the command-line interface included with Laravel.')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/12.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays a message when no results are found using --query option', function (): void {
        // Act & Assert
        $this->artisan(AskDocsCommand::class, ['--query' => 'reverb'])
            ->expectsOutput('No results found for your query.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('handles multiple search results when using --query option', function (): void {
        // Ensure we have multiple entries (reusing the setup from the previous test)
        if (! $this->connection->table('docs')->where('heading', 'Writing Commands')->exists()) {
            $this->connection->table('docs')->insert([
                'title' => 'Artisan Console',
                'heading' => 'Writing Commands',
                'markdown' => 'In addition to the commands provided with Artisan, you may build your own custom commands.',
                'content' => 'In addition to the commands provided with Artisan, you may build your own custom commands.',
                'path' => 'artisan.md',
                'version' => $this->version->value,
                'link' => 'https://laravel.com/docs/12.x/artisan#writing-commands',
            ]);
        }

        // Act & Assert
        $this->artisan(AskDocsCommand::class, ['--query' => 'artisan'])
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Artisan Console - Introduction')
            ->expectsOutputToContain('Artisan Console - Writing Commands')
            ->expectsOutputToContain('In addition to the commands provided with Artisan, you may build your own custom commands.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('formats markdown with proper syntax highlighting', function (): void {
        // Arrange - Insert test data with various markdown elements
        $this->connection->table('docs')->insert([
            'title' => 'Markdown Test',
            'heading' => 'Formatting',
            'markdown' => "# Heading 1\n\n## Heading 2\n\n### Heading 3\n\n".
                "**Bold text** and *italic text*\n\n".
                "- List item 1\n- List item 2\n\n".
                "1. Numbered item 1\n2. Numbered item 2\n\n".
                "```php\necho 'Code block';\n```\n\n".
                "Inline `code` example\n\n".
                '[Link text](https://example.com)',
            'content' => 'Markdown formatting test',
            'path' => 'markdown-test.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/markdown-test',
        ]);

        // Act & Assert - We're not checking specific formatting here, just that it doesn't error
        // and that the content is still present, but h1 headings should be skipped
        $this->artisan(AskDocsCommand::class, ['--query' => 'markdown formatting'])
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Markdown Test - Formatting')
            ->doesntExpectOutputToContain('Heading 1') // h1 headings should be skipped
            ->expectsOutputToContain('Heading 2') // h2 headings should be displayed
            ->expectsOutputToContain('Heading 3') // h3 headings should be displayed
            ->expectsOutputToContain('Bold text')
            ->expectsOutputToContain('List item 1')
            ->expectsOutputToContain('Numbered item 1')
            ->expectsOutputToContain('Code block')
            ->expectsOutputToContain('Inline `code` example')
            ->expectsOutputToContain('Link text')
            ->assertExitCode(Command::SUCCESS);
    });

    it('excludes h1 headings from search results', function (): void {
        // Arrange - Insert test data with h1 heading (title equals heading)
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
        $this->artisan(AskDocsCommand::class, ['--query' => 'heading'])
            ->expectsOutput('🔍 Found relevant information:')
            ->doesntExpectOutputToContain('H1 Heading') // h1 heading should be excluded
            ->expectsOutputToContain('Test Document - H2 Section') // h2 heading should be included
            ->expectsOutputToContain('This is content under an h2 heading.')
            ->assertExitCode(Command::SUCCESS);
    });
});
