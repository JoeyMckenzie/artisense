<?php

declare(strict_types=1);

namespace Artisense;

use Artisense\Console\Commands\InstallCommand;
use Artisense\Console\Commands\SearchDocsCommand;
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
        }
    }
}
