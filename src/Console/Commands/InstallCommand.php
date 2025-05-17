<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Artisense;
use Artisense\Contracts\Actions\UnzipsDocsArchiveAction;
use Artisense\Contracts\Support\StorageManager;
use Artisense\Exceptions\FailedToUnzipArchiveException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Http\Client\Factory as Http;

final class InstallCommand extends Command
{
    public $signature = 'artisense:install';

    public $description = 'Installs and initializes Artisense by downloading Laravel documentation.';

    public function handle(
        UnzipsDocsArchiveAction $action,
        Files $files,
        Http $http,
        StorageManager $storage,
    ): int {
        $this->info('🔧 Installing Artisense...');
        $this->line('Fetching Laravel docs from GitHub...');

        $response = $http->get(Artisense::GITHUB_SOURCE_ZIP);

        if (! $response->ok()) {
            $this->error('Failed to download docs from GitHub.');

            return self::FAILURE;
        }

        $storage->ensureDirectoriesExist();
        $storage->put('laravel-docs.zip', $response->body());

        $this->line('Unzipping docs...');

        $extractedZipPath = $storage->path('laravel-docs.zip');
        $extractPath = $storage->getBasePath();

        try {
            $action->handle($extractedZipPath, $extractPath);
        } catch (FailedToUnzipArchiveException $e) {
            $this->error('Failed to unzip docs: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('Moving docs to subfolder...');

        $markdownFiles = $storage->files('docs-master');

        foreach ($markdownFiles as $file) {
            $source = $storage->path("docs-master/$file");
            $target = $storage->path('docs/'.basename($file));
            $files->move($source, $target);
        }

        $this->line('Removing temporary files...');

        $storage->delete('laravel-docs.zip');
        $storage->deleteDirectory('docs-master');

        $this->info('✅ Laravel docs downloaded and ready!');

        return self::SUCCESS;
    }
}
