<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\ArtisenseConfiguration;
use Artisense\Enums\DocumentationVersion;
use Artisense\Enums\SearchPreference;
use Artisense\Models\DocumentationEntry;
use Illuminate\Console\Command;

use function Laravel\Prompts\search;

final class SearchDocsCommand extends Command
{
    public $signature = 'artisense:search';

    public $description = 'Ask questions about Laravel documentation and get relevant information.';

    public function handle(ArtisenseConfiguration $config): int
    {
        $result = search(
            label: 'Enter a search term to find relevant information:',
            options: fn (string $value): array => $this->getSearchResults($value, $config),
            placeholder: 'Installing Reverb, handling Stripe webhooks, etc.',
            hint: 'Use at least a few characters to get relevant results.',
        );

        if (is_string($result)) {
            $rowid = $this->getRowId($result);
            $entry = DocumentationEntry::query()
                ->where('rowid', $rowid)
                ->first([
                    'title',
                    'heading',
                    'markdown',
                    'link',
                    'version',
                ]);

            if ($entry === null) {
                $this->error(sprintf('No content found for rowid %d.', $rowid));

                return self::FAILURE;
            }

            $title = $entry->title;
            $heading = $entry->heading;
            $markdown = $entry->markdown;
            $link = $entry->link;
            $rowVersion = $entry->version;

            $this->info("<fg=yellow;options=bold>$title - $heading - $rowVersion</>");

            $formatted = $config->formatter->format($markdown);
            $this->line($formatted);

            $this->info("<fg=blue>Learn more: $link</>");
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getSearchResults(string $value, ArtisenseConfiguration $config): array
    {
        if (mb_strlen($value) < 3) {
            return [];
        }

        $ftsQuery = match ($config->preference) {
            SearchPreference::ORDERED => $value,
            SearchPreference::UNORDERED => "NEAR($value, $config->proximity)"
        };

        if ($config->version instanceof DocumentationVersion) {
            $results = DocumentationEntry::query()
                ->whereRaw('content MATCH ?', [$ftsQuery])
                ->where('heading', '!=', 'title')
                ->where('title', '!=', '[Untitled]')
                ->where('version', $config->version->value)
                ->orderByRaw('rank')
                ->limit(5)
                ->get(['rowid', 'title', 'heading', 'version']);
        } else {
            $results = DocumentationEntry::query()
                ->whereRaw('content MATCH ?', [$ftsQuery])
                ->where('heading', '!=', 'title')
                ->where('title', '!=', '[Untitled]')
                ->whereIn('version', collect($config->version)->map(fn (DocumentationVersion $version) => $version->value))
                ->orderByRaw('rank')
                ->limit(5)
                ->get(['rowid', 'title', 'heading', 'version']);
        }

        /** @var string[] $values */
        $values = count($results) > 0
            ? collect($results)
                ->map(fn (DocumentationEntry $result): string => sprintf('%d - %s - %s - %s', $result->rowid, $result->version, $result->title, $result->heading))
                ->toArray()
            : [];

        return $values;
    }

    private function getRowId(string $result): int
    {
        $parsed = explode(' - ', $result);
        $rowid = $parsed[0];

        return (int) $rowid;
    }
}
