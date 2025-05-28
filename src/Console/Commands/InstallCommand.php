<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Console\Concerns\ValidatesVersionOption;
use Artisense\Contracts\Actions\DownloadDocsActionContract;
use Artisense\Contracts\Actions\SeedDocsActionContract;
use Artisense\Exceptions\ArtisenseException;
use Artisense\Exceptions\DocumentationVersionException;
use Artisense\Repository\ArtisenseRepositoryManager;
use Artisense\Support\Services\VersionManager;
use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    use ValidatesVersionOption;

    public $signature = 'artisense:install {--docVersion= : Version of Laravel documentation to use}';

    public $description = 'Installs artisesnse for the project.';

    public function handle(
        VersionManager $versionManager,
        ArtisenseRepositoryManager $repositoryManager,
        DownloadDocsActionContract $downloadDocsAction,
        SeedDocsActionContract $seedDocsAction,
    ): int {
        $this->info('🔧 Installing artisense...');

        try {
            $version = $this->getVersion($versionManager);
        } catch (DocumentationVersionException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("️Using version $version->value...");

        try {
            $this->line('Initializing database...');
            $repositoryManager->initializeDatabase();

            $this->line('Downloading documentation...');
            $downloadDocsAction->handle($version);

            $this->line('Storing documentation...');
            $seedDocsAction->handle($version);
        } catch (ArtisenseException $e) {
            $this->error(sprintf('Failed to install: %s', $e->getMessage()));

            return self::FAILURE;
        }

        $this->info('✅  Artisense is ready!');

        return self::SUCCESS;
    }
}
