<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\DocumentationVersionException;
use Artisense\Support\Services\StorageManager;
use Artisense\Support\Services\VersionManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Validation\Rule;
use ZipArchive;

final class DownloadDocsCommand extends Command
{
    public $signature = 'artisense:download-docs {--docVersion= : Version of Laravel documentation to use}';

    public $description = 'Downloads and unzips Artisense by downloading Laravel documentation.';

    public function handle(
        Http $http,
        StorageManager $storage,
        VersionManager $versionManager,
        Factory $validator
    ): int {
        $flags = [
            'docVersion' => $this->option('docVersion'),
        ];

        $rule = $validator->make($flags, [
            'docVersion' => ['nullable', Rule::enum(DocumentationVersion::class)],
        ]);

        if ($rule->fails()) {
            $this->error($rule->errors()->first());

            return self::FAILURE;
        }

        $this->line('🔧 Downloading documents...');

        if ($flags['docVersion'] !== null) {
            $versionManager->setVersion($flags['docVersion']);
        }

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
