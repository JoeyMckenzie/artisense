<?php

declare(strict_types=1);

namespace Artisense\Support;

use Artisense\Contracts\Support\StorageManager;

final class DiskManager implements StorageManager
{
    public function ensureDirectoriesExist(): void
    {
        $paths = [
            storage_path($this->getStorageKey()),
            storage_path(sprintf('%s/docs', $this->getStorageKey())),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                mkdir($path);
            }
        }
    }

    public function getStorageKey(): string
    {
        return 'artisense';
    }

    public function put(string $filepath, string $contents): void
    {
        $filepath = sprintf('%s/%s', $this->getStorageKey(), $filepath);
        file_put_contents(storage_path($filepath), $contents);
    }

    public function getBasePath(): string
    {
        return storage_path($this->getStorageKey());
    }

    public function files(string $filepath): array
    {
        $directory = $this->path($filepath);

        return array_diff(scandir($directory), ['.', '..']);
    }

    public function path(string $filepath): string
    {
        $path = sprintf('%s/%s', $this->getStorageKey(), $filepath);

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
