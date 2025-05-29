<?php

declare(strict_types=1);

namespace Artisense\Support;

/**
 * @internal
 */
final class StorageManager
{
    public string $zipsPath {
        get {
            return $this->path('zips');
        }
    }

    public string $docsPath {
        get {
            return $this->path('docs');
        }
    }

    public string $dbPath {
        get {
            return $this->path('artisense.sqlite');
        }
    }

    private readonly string $storageKey;

    public function __construct()
    {
        $this->storageKey = 'artisense';
    }

    public function ensureStorageDirectoriesExists(): void
    {
        $storagePath = storage_path($this->storageKey);
        $paths = [
            'zips',
        ];

        foreach ($paths as $path) {
            $folderPath = sprintf('%s%s%s', $storagePath, DIRECTORY_SEPARATOR, $path);
            if (! is_dir($folderPath)) {
                mkdir($folderPath, recursive: true);
            }
        }

    }

    public function ensureDirectoryExists(string $path): void
    {
        $storagePath = storage_path($this->storageKey);
        $path = sprintf('%s%s%s', $storagePath, DIRECTORY_SEPARATOR, $path);

        if (! is_dir($path)) {
            mkdir($path);
        }
    }

    public function put(string $filepath, string $contents): void
    {
        $filepath = sprintf('%s%s%s', $this->storageKey, DIRECTORY_SEPARATOR, $filepath);
        file_put_contents(storage_path($filepath), $contents);
    }

    public function getBasePath(): string
    {
        return storage_path($this->storageKey);
    }

    /**
     * @return string[]
     */
    public function files(string $filepath): array
    {
        $directory = $this->path($filepath);

        return array_diff(scandir($directory), ['.', '..']);
    }

    public function path(string $filepath): string
    {
        $path = sprintf('%s%s%s', $this->storageKey, DIRECTORY_SEPARATOR, $filepath);

        return storage_path($path);
    }

    public function delete(string $filepath): void
    {
        unlink($this->path($filepath));
    }

    public function deleteDirectory(string $path): void
    {
        rmdir($this->path($path));
    }
}
