<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Artisense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ZipArchive;

final class InstallCommand extends Command
{
    public $signature = 'artisense:install';

    public $description = 'Installs and initializes Artisense by downloading Laravel documentation.';

    public function handle(): int
    {
        $this->info('🔧 Installing Artisense...');
        $this->line('Fetching Laravel docs from GitHub...');

        self::ensureArtisenseDirsExist();

        $response = Http::get(Artisense::GITHUB_SOURCE_ZIP);

        if (! $response->ok()) {
            $this->error('Failed to download docs from GitHub.');

            return self::FAILURE;
        }

        file_put_contents($this->zipPath(), $response->body());

        $this->line('Unzipping docs...');

        $zip = new ZipArchive;
        if ($zip->open($this->zipPath()) !== true) {
            $this->error('Failed to unzip docs.');

            return self::FAILURE;
        }

        $zip->extractTo($this->extractPath());
        $zip->close();

        $this->line('Moving docs to subfolder...');

        $markdownFiles = File::glob("{$this->extractPath()}/docs-master/*.md");

        foreach ($markdownFiles as $file) {
            File::move($file, $this->targetDocsPath().'/'.basename((string) $file));
        }

        $this->line('Removing temporary files...');

        unlink($this->zipPath());
        File::deleteDirectory("{$this->extractPath()}/docs-master");

        $this->info('✅ Laravel docs downloaded and ready in: storage/app/artisense/docs');

        return self::SUCCESS;
    }

    private function ensureArtisenseDirsExist(): void
    {
        $paths = [
            storage_path('app/artisense'),
            storage_path('app/artisense/docs'),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    private function zipPath(): string
    {
        return storage_path('app/artisense/laravel-docs.zip');
    }

    private function extractPath(): string
    {
        return storage_path('app/artisense');
    }

    private function targetDocsPath(): string
    {
        return storage_path('app/artisense/docs');
    }
}
