<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Artisense;
use Artisense\Contracts\Actions\UnzipsDocsArchiveAction;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final class InstallCommand extends Command
{
    public $signature = 'artisense:install';

    public $description = 'Installs and initializes Artisense by downloading Laravel documentation.';

    private Filesystem $disk;

    public function handle(UnzipsDocsArchiveAction $action): int
    {
        $this->info('🔧 Installing Artisense...');
        $this->line('Fetching Laravel docs from GitHub...');

        $this->disk = Storage::disk('local');

        self::ensureArtisenseDirsExist();

        $response = Http::get(Artisense::GITHUB_SOURCE_ZIP);

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
        } catch (Exception $e) {
            $this->error('Failed to unzip docs: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('Moving docs to subfolder...');

        $markdownFiles = $this->disk->files('artisense/docs-master');

        foreach ($markdownFiles as $file) {
            File::move($this->disk->path($file), $this->disk->path('artisense/docs/'.basename($file)));
        }

        $this->line('Removing temporary files...');

        $this->disk->delete('artisense/laravel-docs.zip');
        $this->disk->deleteDirectory('artisense/docs-master');

        $this->info('✅ Laravel docs downloaded and ready in: storage/app/artisense/docs');

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
