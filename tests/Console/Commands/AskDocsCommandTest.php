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
        // and that the content is still present
        $this->artisan(AskDocsCommand::class, ['--query' => 'markdown formatting'])
            ->expectsOutput('🔍 Found relevant information:')
            ->expectsOutputToContain('Markdown Test - Formatting')
            ->expectsOutputToContain('Heading 1')
            ->expectsOutputToContain('Heading 2')
            ->expectsOutputToContain('Bold text')
            ->expectsOutputToContain('List item 1')
            ->expectsOutputToContain('Numbered item 1')
            ->expectsOutputToContain('Code block')
            ->expectsOutputToContain('Inline `code` example')
            ->expectsOutputToContain('Link text')
            ->assertExitCode(Command::SUCCESS);
    });
});
