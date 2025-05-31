<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Actions\CleanupArtifactsAction;
use Illuminate\Console\Command;

final class CleanupArtifactsCommand extends Command
{
    public $signature = 'artisense:clean';

    public $description = 'Removes artifacts from storage.';

    public function handle(CleanupArtifactsAction $cleanupArtifactsAction): int
    {
        $this->info('🗑️ Removing artifacts...');

        $cleanupArtifactsAction->handle();

        $this->info('✅  Artifacts removed!');

        return self::SUCCESS;
    }
}
