<?php

declare(strict_types=1);

namespace Artisense\Actions;

use Artisense\Contracts\Actions\GenerateEmbeddingsActionContract;
use Artisense\Enums\DocumentationVersion;
use Artisense\Repository\ArtisenseRepositoryManager;
use Illuminate\Config\Repository as Config;

final readonly class GenerateEmbeddingsAction implements GenerateEmbeddingsActionContract
{
    public function __construct(
        private ArtisenseRepositoryManager $repositoryManager,
        private Config $config,
    ) {
        //
    }

    public function handle(DocumentationVersion $version): void
    {
        $repository = $this->repositoryManager->newConnection();
    }
}
