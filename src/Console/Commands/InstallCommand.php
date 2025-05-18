<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Contracts\StorageManager;
use Artisense\Enums\DocumentationVersion;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Http\Client\Factory as Http;
use ZipArchive;

final class InstallCommand extends Command
{
    public $signature = 'artisense:install';

    public $description = 'Installs and initializes Artisense by downloading Laravel documentation.';

    private Repository $config;

    public function handle(
        Files $files,
        Http $http,
        StorageManager $storage,
        Repository $config
    ): int {
        $this->config = $config;
        $version = $this->getVersion();
        $this->info('🔧 Installing Artisense...');
        $this->line('Fetching Laravel docs from GitHub...');

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
        $this->unzipDocsFile($extractedZipPath, $extractPath);

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

    private function getVersion(): DocumentationVersion
    {
        $value = $this->config->get('artisense.version');

        if ($value instanceof DocumentationVersion) {
            return $value;
        }

        if ($value === null) {
            return self::getDefaultVersion();
        }

        if (! is_string($value)) {
            $this->error("Documentation version must be a valid version string (e.g., '12.x', '11.x', 'master', etc.).");

            exit(self::FAILURE);
        }

        assert(is_string($value));
        $version = DocumentationVersion::tryFrom($value);

        if ($version === null) {
            return self::getDefaultVersion();
        }

        return $version;
    }

    private function getDefaultVersion(): DocumentationVersion
    {
        $this->line('No documentation version specified, using lastest version (12.x) by default.');

        return DocumentationVersion::VERSION_12;
    }

    private function unzipDocsFile(string $extractedZipPath, string $extractPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($extractedZipPath) !== true) {
            $this->error('Failed to unzip docs.');

            exit(self::FAILURE);
        }

        $zip->extractTo($extractPath);
        $zip->close();
    }
}
