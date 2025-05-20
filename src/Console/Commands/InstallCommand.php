<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;

final class InstallCommand extends Command
{
    public $signature = 'artisense:install';

    public $description = 'Installs Artisesnse for the project.';

    public function handle(Kernel $artisan): int
    {
        $this->info('🔧 Install Artisense...');

        $artisan->call(DownloadDocsCommand::class);
        $artisan->call(SeedDocsCommand::class);

        $this->info('✅ Laravel docs downloaded and ready!');

        return self::SUCCESS;
    }
}
