<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Repository\ArtisenseRepositoryManager;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

final class AskDocsCommand extends Command
{
    public $signature = 'artisense:ask';

    public $description = 'Ask questions about Laravel documentation and get relevant information.';

    public function handle(ArtisenseRepositoryManager $repositoryManager): int
    {
        $question = text(
            label: 'What are you looking for?',
            required: true
        );

        $repository = $repositoryManager->newConnection();
        $results = $repository->search($question);

        if (empty($results)) {
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
