<?php

declare(strict_types=1);

namespace Artisense\Contracts\Actions;

use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\ArtisenseException;

interface ActionContract
{
    /**
     * @throws ArtisenseException
     */
    public function handle(DocumentationVersion $version): void;
}
