<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Exceptions\DocumentationVersionException;
use Artisense\Support\DiskManager;
use Artisense\Support\VersionManager;
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
        DiskManager $storage,
        VersionManager $versionManager,
    ): int {
        $this->info('🔧 Downloading documents...');

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

        $storage->ensureDirectoriesExist();
        $storage->put('laravel-docs.zip', $response->body());

        $this->line('Unzipping docs...');

        $extractedZipPath = $storage->path('laravel-docs.zip');
        $extractPath = $storage->getBasePath();
        $resultCode = $this->unzipDocsFile($extractedZipPath, $extractPath);

        if ($resultCode === self::FAILURE) {
            return self::FAILURE;
        }

        $this->line('Moving docs to subfolder...');

        $extractedFolder = pathinfo($version->getExtractedFileName(), PATHINFO_FILENAME);
        $markdownFiles = $storage->files($extractedFolder);

        foreach ($markdownFiles as $file) {
            $source = $storage->path("$extractedFolder/$file");
            $target = $storage->path('docs/'.basename($file));
            $files->move($source, $target);
        }

        $this->line('Removing temporary files...');

        $storage->delete('laravel-docs.zip');
        $storage->deleteDirectory($extractedFolder);

        $this->info('✅ Laravel docs downloaded and ready!');

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
