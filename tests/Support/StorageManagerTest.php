<?php

declare(strict_types=1);

namespace Artisense\Tests\Support;

use Artisense\Support\StorageManager;
use ErrorException;
use Illuminate\Support\Facades\File;

covers(StorageManager::class);

describe(StorageManager::class, function (): void {
    beforeEach(function (): void {
        $this->storageManager = app(StorageManager::class);
        File::deleteDirectory(storage_path('artisense'));
    });

    it('initializes with the correct storage key', function (): void {
        // Arrange & Act
        $basePath = $this->storageManager->getBasePath();

        // Assert
        expect($basePath)->toBe(storage_path('artisense'));
    });

    it('ensures directories exist', function (): void {
        // Arrange
        expect(File::exists(storage_path('artisense')))->toBeFalse();

        // Act
        $this->storageManager->ensureStorageDirectoriesExists();

        // Assert
        expect(File::exists(storage_path('artisense')))->toBeTrue();
    });

    it('ensures gitignore exists', function (): void {
        // Arrange
        expect(File::exists(storage_path('artisense')))->toBeFalse();

        // Act
        $this->storageManager->ensureStorageDirectoriesExists();

        // Assert
        expect(File::exists(storage_path('artisense')))
            ->and(File::exists(storage_path('artisense/.gitignore')))->toBeTrue();
    });

    it('puts content into a file', function (): void {
        // Arrange
        $this->storageManager->ensureStorageDirectoriesExists();
        $testContent = 'Test content for file';
        $testFilePath = 'test-file.txt';

        // Act
        $this->storageManager->put($testFilePath, $testContent);

        // Assert
        $fullPath = storage_path('artisense/'.$testFilePath);
        expect(File::exists($fullPath))->toBeTrue();
        expect(File::get($fullPath))->toBe($testContent);
    });

    it('returns the base path', function (): void {
        // Arrange & Act
        $basePath = $this->storageManager->getBasePath();

        // Assert
        expect($basePath)->toBe(storage_path('artisense'));
    });

    it('returns a list of files in a directory', function (): void {
        // Arrange
        $this->storageManager->ensureStorageDirectoriesExists();
        $this->storageManager->put('file1.txt', 'Content 1');
        $this->storageManager->put('file2.txt', 'Content 2');

        // Act
        $files = $this->storageManager->files('');

        // Assert
        expect($files)->toContain('file1.txt');
        expect($files)->toContain('file2.txt');
    });

    it('constructs the correct path', function (): void {
        // Arrange
        $testPath = 'test/path/file.txt';

        // Act
        $fullPath = $this->storageManager->path($testPath);

        // Assert
        expect($fullPath)->toBe(storage_path('artisense/test/path/file.txt'));
    });

    it('deletes a file', function (): void {
        // Arrange
        $this->storageManager->ensureStorageDirectoriesExists();
        $testFilePath = 'file-to-delete.txt';
        $this->storageManager->put($testFilePath, 'Delete me');
        $fullPath = storage_path('artisense/'.$testFilePath);
        expect(File::exists($fullPath))->toBeTrue();

        // Act
        $this->storageManager->delete($testFilePath);

        // Assert
        expect(File::exists($fullPath))->toBeFalse();
    });

    it('deletes a directory', function (): void {
        // Arrange
        $this->storageManager->ensureStorageDirectoriesExists();
        $testDirPath = 'test-dir';
        $fullPath = storage_path('artisense/'.$testDirPath);
        File::makeDirectory($fullPath);
        expect(File::exists($fullPath))->toBeTrue();

        // Act
        $this->storageManager->deleteDirectory($testDirPath);

        // Assert
        expect(File::exists($fullPath))->toBeFalse();
    });

    it('handles non-existent directories when listing files', function (): void {
        // Arrange
        $nonExistentPath = 'non-existent-directory';

        // Act & Assert
        expect(fn () => $this->storageManager->files($nonExistentPath))
            ->toThrow(ErrorException::class);
    });
});
