<?php

declare(strict_types=1);

namespace Artisense;

use Artisense\Actions\DownloadDocsAction;
use Artisense\Actions\SeedDocsAction;
use Artisense\Console\Commands\InstallCommand;
use Artisense\Console\Commands\SearchDocsCommand;
use Artisense\Contracts\Actions\DownloadDocsActionContract;
use Artisense\Contracts\Actions\SeedDocsActionContract;
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
                SearchDocsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/artisense.php' => config_path('artisense.php'),
            ], 'artisense-config');

            $this->app->bind(DownloadDocsActionContract::class, DownloadDocsAction::class);
            $this->app->bind(SeedDocsActionContract::class, SeedDocsAction::class);
        }
    }
}
