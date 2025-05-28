<?php

declare(strict_types=1);

namespace Artisense\Tests\Support;

use Artisense\Support\Services\DocumentationDatabaseManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

covers(DocumentationDatabaseManager::class);

describe(DocumentationDatabaseManager::class, function (): void {
    beforeEach(function (): void {
        $this->manager = app(DocumentationDatabaseManager::class);
        if (File::exists($this->dbPath)) {
            File::delete($this->dbPath);
        }
    });

    it('initializes the database successfully', function (): void {
        // Arrange & Act
        $this->manager->initializeDatabase();

        // Assert
        expect(File::exists($this->dbPath))->toBeTrue();

        // Verify the connection is configured
        $connection = DB::connection('artisense');
        expect($connection->getDatabaseName())->toBe($this->dbPath);

        // Verify the docs table exists
        $tableExists = $connection->selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name='docs'");
        expect($tableExists)->not->toBeNull();
    });

    it('creates the docs table with the correct schema', function (): void {
        // Arrange & Act
        $this->manager->initializeDatabase();
        $connection = DB::connection('artisense');
        $tableInfo = $connection->select('PRAGMA table_info(docs)');
        $columns = collect($tableInfo)->pluck('name')->toArray();

        // Assert
        expect($columns)->toContain('title')
            ->and($columns)->toContain('heading')
            ->and($columns)->toContain('markdown')
            ->and($columns)->toContain('content')
            ->and($columns)->toContain('embedding')
            ->and($columns)->toContain('path')
            ->and($columns)->toContain('version')
            ->and($columns)->toContain('link');
    });

    it('does not recreate the docs table if it already exists', function (): void {
        // Arrange, first initialization
        $this->manager->initializeDatabase();

        // Insert a test record
        $this->connection->insert('INSERT INTO docs (title, content) VALUES (?, ?)', ['Test Title', 'Test Content']);

        // Act, second initialization
        $this->manager->initializeDatabase();

        // Assert, verify our test record still exists
        $record = $this->connection->selectOne('SELECT * FROM docs WHERE title = ?', ['Test Title']);
        expect($record)->not->toBeNull()
            ->and($record->content)->toBe('Test Content');
    });
});
