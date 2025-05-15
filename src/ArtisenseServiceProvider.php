<?php

declare(strict_types=1);

namespace Artisense;

use Artisense\Artisense\Commands\ArtisenseCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ArtisenseServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('artisense')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_artisense_table')
            ->hasCommand(ArtisenseCommand::class);
    }
}
