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
});
