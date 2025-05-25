<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Actions\DownloadDocsAction;
use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\ArtisenseException;
use Artisense\Exceptions\DocumentationVersionException;
use Artisense\Support\Services\VersionManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;

final class InstallCommand extends Command
{
    public $signature = 'artisense:install {--docVersion= : Version of Laravel documentation to use}';

    public $description = 'Installs Artisesnse for the project.';

    public function handle(
        Kernel $artisan,
        VersionManager $versionManager,
        DownloadDocsAction $downloadDocsAction
    ): int {
        $this->info('🔧 Installing artisense...');

        $versionOption = $this->option('docVersion');

        if ($versionOption !== null) {
            $version = DocumentationVersion::tryFrom($versionOption);

            if ($version === null) {
                $validVersions = implode(', ', DocumentationVersion::values());
                $message = sprintf('Invalid version "%s" provided, please use one of the following: %s', $versionOption, $validVersions);
                $this->error($message);

                return self::FAILURE;
            }

            $versionManager->setVersion($version);
        }

        try {
            $version = $versionManager->getVersion();
        } catch (DocumentationVersionException $e) {
            $this->error(sprintf('Failed to get version: %s', $e->getMessage()));

            return self::FAILURE;
        }

        $this->info("️📕 Using version $version->value");

        try {
            $downloadDocsAction->handle($version);
        } catch (ArtisenseException $e) {
            $this->error(sprintf('Failed to download docs: %s', $e->getMessage()));

            return self::FAILURE;
        }

        $this->info('ℹ️ Documents extracted, seeding database...');

        $artisan->call(SeedDocsCommand::class, ['--docVersion' => $this->option('docVersion')]);

        $this->info('✅  Artisense is ready!');

        return self::SUCCESS;
    }
}
