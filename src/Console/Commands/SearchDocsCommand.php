<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\ArtisenseConfiguration;
use Artisense\Enums\DocumentationVersion;
use Artisense\Enums\SearchPreference;
use Artisense\Exceptions\ArtisenseConfigurationException;
use Artisense\Exceptions\InvalidOutputFormatterException;
use Artisense\Models\DocumentationEntry;
use Illuminate\Console\Command;

use function Laravel\Prompts\search;

final class SearchDocsCommand extends Command
{
    public $signature = 'artisense:search';

    public $description = 'Ask questions about Laravel documentation and get relevant information.';

    public function handle(ArtisenseConfiguration $config): int
    {
        try {
            $version = $config->getVersion();
            $preference = $config->getSearchPreference();
            $proximity = $config->getSearchProximity();
        } catch (ArtisenseConfigurationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $result = search(
            label: 'Enter a search term to find relevant information:',
            options: fn (string $value): array => $this->getSearchResults($value, $version, $preference, $proximity),
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
                ]);

            if ($entry === null) {
                $this->error(sprintf('No content found for rowid %d.', $rowid));

                return self::FAILURE;
            }

            $title = $entry->title;
            $heading = $entry->heading;
            $markdown = $entry->markdown;
            $link = $entry->link;

            $this->info("<fg=yellow;options=bold>$title - $heading - $version->value</>");

            try {
                $formatter = $config->getOutputFormatter();
                $formatted = $formatter->format($markdown);
                $this->line($formatted);
            } catch (InvalidOutputFormatterException $e) {
                $this->warn('Failed to format markdown with the configured formatter.');
                $this->warn($e->getMessage());
            }

            $this->info("<fg=blue>Learn more: $link</>");
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getSearchResults(
        string $value,
        DocumentationVersion $version,
        SearchPreference $preference,
        int $searchProximity): array
    {
        if (mb_strlen($value) < 3) {
            return [];
        }

        $ftsQuery = match ($preference) {
            SearchPreference::ORDERED => $value,
            SearchPreference::UNORDERED => "NEAR($value, $searchProximity)"
        };

        $results = DocumentationEntry::query()
            ->whereRaw('content MATCH ?', [$ftsQuery])
            ->where('heading', '!=', 'title')
            ->where('title', '!=', '[Untitled]')
            ->where('version', $version->value)
            ->orderByRaw('rank')
            ->limit(5)
            ->get(['rowid', 'title', 'heading', 'version']);

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
