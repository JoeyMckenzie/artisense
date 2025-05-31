<?php

declare(strict_types=1);

namespace Artisense\Contracts\Actions;

interface CleanupArtifactsActionContract
{
    public function handle(): void;
}
