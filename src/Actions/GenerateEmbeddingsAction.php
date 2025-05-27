<?php

declare(strict_types=1);

namespace Artisense\Actions;

use Artisense\Contracts\Actions\GenerateEmbeddingsActionContract;
use Artisense\Enums\DocumentationVersion;
use Artisense\Repository\ArtisenseRepositoryManager;
use Artisense\Support\Services\OpenAIConnector;

final readonly class GenerateEmbeddingsAction implements GenerateEmbeddingsActionContract
{
    public function __construct(
        private ArtisenseRepositoryManager $repositoryManager,
        private OpenAIConnector $connector,
    ) {
        //
    }

    public function handle(DocumentationVersion $version): void
    {
        $repository = $this->repositoryManager->newConnection();
        $embedding = $this->connector->generateEmbedding('test');
    }
}
