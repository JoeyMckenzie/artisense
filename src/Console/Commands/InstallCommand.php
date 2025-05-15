<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Artisense;
use Artisense\Contracts\Actions\UnzipsDocsArchiveAction;
use Artisense\Exceptions\FailedToUnzipArchiveException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem as Disk;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Client\Factory as Http;

final class InstallCommand extends Command
{
    public $signature = 'artisense:install';

    public $description = 'Installs and initializes Artisense by downloading Laravel documentation.';

    private Disk $disk;

    public function handle(
        UnzipsDocsArchiveAction $action,
        FilesystemManager $storage,
        Files $files,
        Http $http,
    ): int {
        $this->info('🔧 Installing Artisense...');
        $this->line('Fetching Laravel docs from GitHub...');

        $this->disk = $storage->disk('local');

        self::ensureArtisenseDirsExist();

        $response = $http->get(Artisense::GITHUB_SOURCE_ZIP);

        if (! $response->ok()) {
            $this->error('Failed to download docs from GitHub.');

            return self::FAILURE;
        }

        $this->disk->put('artisense/laravel-docs.zip', $response->body());

        $this->line('Unzipping docs...');

        $extractedZipPath = $this->disk->path('artisense/laravel-docs.zip');
        $extractPath = $this->disk->path('artisense');

        try {
            $action->handle($extractedZipPath, $extractPath);
        } catch (FailedToUnzipArchiveException $e) {
            $this->error('Failed to unzip docs: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('Moving docs to subfolder...');

        $markdownFiles = $this->disk->files('artisense/docs-master');

        foreach ($markdownFiles as $file) {
            $files->move($this->disk->path($file), $this->disk->path('artisense/docs/'.basename($file)));
        }

        $this->line('Removing temporary files...');

        $this->disk->delete('artisense/laravel-docs.zip');
        $this->disk->deleteDirectory('artisense/docs-master');

        $this->info('✅ Laravel docs downloaded and ready!');

        return self::SUCCESS;
    }

    private function ensureArtisenseDirsExist(): void
    {
        $paths = [
            'artisense',
            'artisense/docs',
        ];

        foreach ($paths as $path) {
            if (! $this->disk->exists($path)) {
                $this->disk->makeDirectory($path);
            }
        }
    }
}
