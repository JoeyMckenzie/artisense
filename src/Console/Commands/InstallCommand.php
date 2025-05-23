<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Validation\Factory;

final class InstallCommand extends Command
{
    public $signature = 'artisense:install {--docVersion= : Version of Laravel documentation to use}';

    public $description = 'Installs Artisesnse for the project.';

    public function handle(
        Kernel $artisan,
        Factory $validator
    ): int {
        $this->info('🔧 Installing artisense...');

        $artisan->call(DownloadDocsCommand::class, ['--docVersion' => $this->option('docVersion')]);

        $this->info('ℹ️  Documents extracted, seeding database...');

        $artisan->call(SeedDocsCommand::class, ['--docVersion' => $this->option('docVersion')]);

        $this->info('✅ Artisense is ready!');

        return self::SUCCESS;
    }
}
