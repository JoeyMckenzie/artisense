<?php

declare(strict_types=1);

namespace Artisense\Tests\Console\Commands;

use Artisense\Console\Commands\SearchDocsCommand;
use Artisense\Enums\DocumentationVersion;
use Artisense\Models\DocumentationEntry;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Command\Command;

covers(SearchDocsCommand::class);

describe(SearchDocsCommand::class, function (): void {
    beforeEach(function (): void {
        $this->connection->statement('DROP TABLE IF EXISTS docs');
        $this->connection->statement('CREATE VIRTUAL TABLE docs USING fts5(title, heading, markdown, content, path, version, link)');

        DocumentationEntry::insert([
            'title' => 'Artisan Console',
            'heading' => 'Introduction',
            'markdown' => 'Artisan is the command-line interface included with Laravel.',
            'content' => 'Artisan is the command-line interface included with Laravel.',
            'path' => 'artisan.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/artisan#introduction',
        ]);

        DocumentationEntry::insert([
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
        // Arrange & Act & Assert
        $this->artisan(SearchDocsCommand::class)
            ->expectsSearch('Enter a search term to find relevant information:', '1 - 12.x - Artisan Console - Introduction', 'artisan command', ['1 - 12.x - Artisan Console - Introduction'])
            ->expectsOutputToContain('Artisan Console - Introduction - 12.x')
            ->expectsOutputToContain('Artisan is the command-line interface included with Laravel.')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/12.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);
    });

    it('returns search results when matches are found for multiple versions', function (): void {
        // Arrange
        DocumentationEntry::insert([
            'title' => 'Artisan Console (11.x)',
            'heading' => 'Introduction (11.x)',
            'markdown' => 'Artisan is the command-line interface included with Laravel.',
            'content' => 'Artisan is the command-line interface included with Laravel.',
            'path' => 'artisan.md',
            'version' => DocumentationVersion::VERSION_11,
            'link' => 'https://laravel.com/docs/11.x/artisan#introduction',
        ]);

        Config::set('artisense.version', [
            DocumentationVersion::VERSION_11,
            DocumentationVersion::VERSION_12,
        ]);

        $expectedSearchResults = [
            '1 - 12.x - Artisan Console - Introduction',
            '3 - 11.x - Artisan Console (11.x) - Introduction (11.x)',
        ];

        // Act & Assert
        $this->artisan(SearchDocsCommand::class)
            ->expectsSearch('Enter a search term to find relevant information:', '3 - 11.x - Artisan Console (11.x) - Introduction - (11.x)', 'artisan command', $expectedSearchResults)
            ->expectsOutputToContain('Artisan Console (11.x) - Introduction (11.x) - 11.x')
            ->expectsOutputToContain('Artisan is the command-line interface included with Laravel.')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/11.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);
    });

    it('handles multiple search results', function (): void {
        // Arrange & Act & Assert
        $this->artisan(SearchDocsCommand::class)
            ->expectsSearch('Enter a search term to find relevant information:', '1 - 12.x - Artisan Console - Introduction', 'artisan', [
                '1 - 12.x - Artisan Console - Introduction',
                '2 - 12.x - Artisan Console - Writing Commands',
            ])
            ->expectsOutputToContain('Artisan Console - Introduction - 12.x')
            ->expectsOutputToContain('Artisan is the command-line interface included with Laravel.')
            ->expectsOutputToContain('Learn more: https://laravel.com/docs/12.x/artisan#introduction')
            ->assertExitCode(Command::SUCCESS);
    });

    it('excludes h1 headings from search results', function (): void {
        // Arrange, insert test data with h1 heading (title equals heading)
        DocumentationEntry::insert([
            'title' => 'H1 Heading',
            'heading' => 'H1 Heading', // This is an h1 heading (title equals heading)
            'markdown' => "# H1 Heading\n\nThis is content under an h1 heading.",
            'content' => 'H1 Heading This is content under an h1 heading.',
            'path' => 'h1-test.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/h1-test',
        ]);

        // Insert a regular section with h2 heading
        DocumentationEntry::insert([
            'title' => 'Test Document',
            'heading' => 'H2 Section', // This is not an h1 heading (title doesn't equal heading)
            'markdown' => "## H2 Section\n\nThis is content under an h2 heading.",
            'content' => 'H2 Section This is content under an h2 heading.',
            'path' => 'h2-test.md',
            'version' => $this->version->value,
            'link' => 'https://laravel.com/docs/12.x/h2-test',
        ]);

        // Act & Assert - Verify that h1 headings are excluded from search results
        $this->artisan(SearchDocsCommand::class)
            ->expectsSearch('Enter a search term to find relevant information:', '4 - 12.x - Test Document - H2 Section', 'H2 Section', ['4 - 12.x - Test Document - H2 Section'])
            ->doesntExpectOutputToContain('H1 Heading') // h1 heading should be excluded
            ->expectsOutputToContain('Test Document - H2 Section - 12.x') // h2 heading should be included
            ->expectsOutputToContain('This is content under an h2 heading.')
            ->assertExitCode(Command::SUCCESS);
    });
});
