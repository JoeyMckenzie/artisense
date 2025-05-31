<?php

declare(strict_types=1);

namespace Artisense;

use Artisense\Actions\CleanupArtifactsAction;
use Artisense\Actions\DownloadDocsAction;
use Artisense\Actions\SeedDocsAction;
use Artisense\Console\Commands\CleanupArtifactsCommand;
use Artisense\Console\Commands\InstallCommand;
use Artisense\Console\Commands\SearchDocsCommand;
use Artisense\Contracts\Actions\CleanupArtifactsActionContract;
use Artisense\Contracts\Actions\DownloadDocsActionContract;
use Artisense\Contracts\Actions\SeedDocsActionContract;
use Artisense\Support\StorageManager;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
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

    public function boot(Config $config, StorageManager $disk): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                SearchDocsCommand::class,
                CleanupArtifactsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/artisense.php' => config_path('artisense.php'),
            ], 'artisense-config');

            $this->app->bind(DownloadDocsActionContract::class, DownloadDocsAction::class);
            $this->app->bind(SeedDocsActionContract::class, SeedDocsAction::class);
            $this->app->bind(CleanupArtifactsActionContract::class, CleanupArtifactsAction::class);
            $this->app->singleton(ArtisenseConfiguration::class, fn (Application $app): ArtisenseConfiguration => ArtisenseConfiguration::init($app));

            Model::unguard();
            Model::shouldBeStrict();

            $config->set([
                'database.connections.artisense' => [
                    'driver' => 'sqlite',
                    'database' => $disk->dbPath,
                    'prefix' => '',
                ],
            ]);
        }
    }
}
