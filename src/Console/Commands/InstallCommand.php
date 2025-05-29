<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\ArtisenseConfiguration;
use Artisense\Contracts\Actions\DownloadDocsActionContract;
use Artisense\Contracts\Actions\SeedDocsActionContract;
use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\ArtisenseConfigurationException;
use Artisense\Exceptions\ArtisenseException;
use Artisense\Support\DocumentationDatabaseManager;
use Illuminate\Console\Command;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\progress;

final class InstallCommand extends Command
{
    public $signature = 'artisense:install';

    public $description = 'Installs artisesnse for the project.';

    private DownloadDocsActionContract $downloadDocsAction;

    private SeedDocsActionContract $seedDocsAction;

    public function handle(
        ArtisenseConfiguration $config,
        DocumentationDatabaseManager $repositoryManager,
        DownloadDocsActionContract $downloadDocsAction,
        SeedDocsActionContract $seedDocsAction,
    ): int {
        $this->info('🔧 Installing artisense...');

        $this->downloadDocsAction = $downloadDocsAction;
        $this->seedDocsAction = $seedDocsAction;

        try {
            $version = $config->getVersion();

            /** @var string[] $versions */
            $versions = multiselect(
                label: 'Which version of documentation would you like to install?',
                options: DocumentationVersion::values(),
                default: [$version->value],
                required: 'You must select at least one version.',
                hint: 'You can change the default version within the artisense.php config file.'
            );

            $versionsToInstall = array_map(
                static fn (string $selectedVersion) => DocumentationVersion::from($selectedVersion), $versions
            );

            $this->line('Initializing database...');
            $repositoryManager->initializeDatabase();

            progress(
                label: 'Installing documentation...',
                steps: $versionsToInstall,
                callback: fn (DocumentationVersion $documentationVersion) => $this->downloadAndInstallVersion($documentationVersion),
                hint: 'This may take a while depending on the size of the documentation you are installing.'
            );

            clear();
        } catch (ArtisenseConfigurationException|ArtisenseException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('✅  Artisense is ready!');
        $this->line('Run `php artisan artisense:search` to explore the documentation.');

        return self::SUCCESS;
    }

    /**
     * @throws ArtisenseException
     */
    private function downloadAndInstallVersion(DocumentationVersion $version): void
    {
        $this->info("Installing version $version->value...");

        $this->line('Downloading documentation...');
        $this->downloadDocsAction->handle($version);

        $this->line('Storing documentation...');
        $this->seedDocsAction->handle($version);
    }
}
