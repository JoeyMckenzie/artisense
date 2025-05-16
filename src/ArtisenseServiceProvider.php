<?php

declare(strict_types=1);

namespace Artisense;

use Artisense\Actions\UnzipDocsArchiveAction;
use Artisense\Console\Commands\InstallCommand;
use Artisense\Console\Commands\ParseDocsCommand;
use Artisense\Contracts\Actions\UnzipsDocsArchiveAction;
use Artisense\Contracts\Support\StorageManager;
use Artisense\Support\DiskManager;
use Illuminate\Support\ServiceProvider;
use Override;

final class ArtisenseServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/artisense.php', 'artisense'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ParseDocsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/artisense.php' => config_path('artisense.php'),
            ], 'artisense-config');
        }

        $this->app->bind(UnzipsDocsArchiveAction::class, UnzipDocsArchiveAction::class);
        $this->app->bind(StorageManager::class, DiskManager::class);
    }
}
