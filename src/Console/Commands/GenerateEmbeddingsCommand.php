<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Actions\GenerateEmbeddingsAction;
use Artisense\Console\Concerns\ValidatesVersionOption;
use Artisense\Exceptions\DocumentationVersionException;
use Artisense\Support\Services\DocumentationDatabaseManager;
use Artisense\Support\Services\VersionManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use stdClass;

final class GenerateEmbeddingsCommand extends Command
{
    use ValidatesVersionOption;

    public $signature = 'artisense:generate-embeddings {--docVersion= : Version of Laravel documentation to use}';

    public $description = 'Generates and stores emebeddings for the locally stored documentation.';

    public function handle(
        VersionManager $versionManager,
        DocumentationDatabaseManager $repositoryManager,
        Config $config,
        GenerateEmbeddingsAction $action
    ): int {
        $this->info('🔧 Generating embeddings...');

        try {
            $version = $this->getVersion($versionManager);
        } catch (DocumentationVersionException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("️Using version $version->value...");

        $repository = $repositoryManager->newConnection();
        $entries = $repository->getContentEntriesForVersion($version);
        $chunkSize = $config->get('artisense.openai_chunk_size');

        if ($chunkSize === null) {
            $chunkSize = 100;
        }

        if (! is_int($chunkSize)) {
            $this->error('Invalid chunk size, must be an integer.');

            return self::FAILURE;
        }

        $entries
            ->chunk($chunkSize)
            ->each(fn (stdClass $chunk) => $action->handle($version));

        $this->info('✅  Artisense is ready!');

        return self::SUCCESS;
    }
}
