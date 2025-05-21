<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Repository\ArtisenseRepositoryManager;
use Illuminate\Console\Command;

use function Laravel\Prompts\textarea;

final class AskDocsCommand extends Command
{
    public $signature = 'artisense:ask {--query= : Search query for documentation}';

    public $description = 'Ask questions about Laravel documentation and get relevant information.';

    public function handle(ArtisenseRepositoryManager $repositoryManager): int
    {
        $query = $this->option('query');

        $question = $query ?? textarea(
            label: 'What are you looking for?',
            required: true
        );

        $repository = $repositoryManager->newConnection();
        $results = $repository->search($question);

        if (count($results) === 0) {
            $this->info('No results found for your query.');

            return self::SUCCESS;
        }

        $this->info('🔍 Found relevant information:');
        $this->newLine();

        foreach ($results as $result) {
            $this->line("<fg=yellow;options=bold>$result->title - $result->heading</>");
            $this->line($result->markdown);
            $this->line("<fg=blue>Learn more: $result->link</>");
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
