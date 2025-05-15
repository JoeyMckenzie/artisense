<?php

declare(strict_types=1);

namespace Artisense\Commands;

use Illuminate\Console\Command;

final class ArtisenseCommand extends Command
{
    public $signature = 'artisense';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
