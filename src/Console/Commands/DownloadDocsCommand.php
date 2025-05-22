<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Exceptions\DocumentationVersionException;
use Artisense\Support\Services\StorageManager;
use Artisense\Support\Services\VersionManager;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Http\Client\Factory as Http;
use ZipArchive;

final class DownloadDocsCommand extends Command
{
    public $signature = 'artisense:download-docs';

    public $description = 'Downloads and unzips Artisense by downloading Laravel documentation.';

    public function handle(
        Files $files,
        Http $http,
        StorageManager $storage,
        VersionManager $versionManager,
    ): int {
        $this->line('🔧 Downloading documents...');

        try {
            $version = $versionManager->getVersion();
        } catch (DocumentationVersionException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("Using version $version->value, fetching Laravel docs from GitHub...");

        $zipUrl = $version->getZipUrl();
        $response = $http->get($zipUrl);

        if (! $response->ok()) {
            $this->error('Failed to download docs from GitHub.');

            return self::FAILURE;
        }

        $storage->ensureDocStorageDirectoryExists();
        $storage->put('laravel-docs.zip', $response->body());

        $this->line('Unzipping docs...');

        $extractedZipPath = $storage->path('laravel-docs.zip');
        $extractPath = $storage->getBasePath();
        $resultCode = $this->unzipDocsFile($extractedZipPath, $extractPath);

        if ($resultCode === self::FAILURE) {
            return self::FAILURE;
        }

        $this->line('Removing temporary files...');

        $storage->delete('laravel-docs.zip');

        $this->line('✅ Laravel docs downloaded and ready!');

        return self::SUCCESS;
    }

    private function unzipDocsFile(string $extractedZipPath, string $extractPath): int
    {
        $zip = new ZipArchive;

        if ($zip->open($extractedZipPath) !== true) {
            $this->error('Failed to unzip docs.');

            return self::FAILURE;
        }

        $zip->extractTo($extractPath);
        $zip->close();

        return self::SUCCESS;
    }
}
