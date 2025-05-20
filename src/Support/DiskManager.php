<?php

declare(strict_types=1);

namespace Artisense\Support;

/**
 * @internal
 */
final readonly class DiskManager
{
    private string $storageKey;

    public function __construct(?string $storageKey)
    {
        $this->storageKey = $storageKey ?? 'artisense';
    }

    public function ensureDirectoriesExist(): void
    {
        $paths = [
            storage_path($this->storageKey),
            storage_path(sprintf('%s/docs', $this->storageKey)),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                mkdir($path);
            }
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
