<?php

declare(strict_types=1);

namespace Artisense\Support\Services;

/**
 * @internal
 */
final readonly class StorageManager
{
    private string $storageKey;

    public function __construct()
    {
        $this->storageKey = 'artisense';
    }

    public function ensureDocStorageDirectoryExists(): void
    {
        $path = storage_path($this->storageKey);

        if (! is_dir($path)) {
            mkdir($path);
        }
    }

    public function put(string $filepath, string $contents): void
    {
        $filepath = sprintf('%s/%s', $this->storageKey, $filepath);
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
        $path = sprintf('%s/%s', $this->storageKey, $filepath);

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
