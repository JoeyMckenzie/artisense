<?php

declare(strict_types=1);

namespace Artisense\Contracts\Actions;

use Artisense\Enums\DocumentationVersion;

interface ActionContract
{
    public function handle(DocumentationVersion $version): void;
}
