<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Artisense;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

final class InstallCommand extends Command
{
    public $signature = 'artisense:install';

    public $description = 'Installs and initializes Artisense by downloading Laravel documentation.';

    private Filesystem $disk;

    public function handle(): int
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

        $saved = $this->disk->put('artisense/laravel-docs.zip', $response->body());

        $this->line('Unzipping docs...');

        $zip = new ZipArchive;
        $extractedZipPath = $this->disk->path('artisense/laravel-docs.zip');

        if ($zip->open($extractedZipPath) !== true) {
            $this->error('Failed to unzip docs.');

            return self::FAILURE;
        }

        $extractPath = $this->disk->path('artisense');
        $zip->extractTo($extractPath);
        $zip->close();

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
