<?php

declare(strict_types=1);

namespace Artisense\Contracts\Support;

interface StorageManager
{
    public function getStorageKey(): string;

    public function getBasePath(): string;

    public function ensureDirectoriesExist(): void;

    public function put(string $filepath, string $contents): void;

    public function delete(string $filepath): void;

    public function deleteDirectory(string $path): void;

    public function path(string $filepath): string;

    /**
     * @return string[]
     */
    public function files(string $filepath): array;
}
