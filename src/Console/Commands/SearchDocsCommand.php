<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Contracts\OutputFormatterContract;
use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\InvalidOutputFormatterException;
use Artisense\Repository\ArtisenseRepositoryManager;
use Artisense\Support\Services\VersionManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as Validator;
use Illuminate\Validation\Rule;
use ReflectionClass;

use function Laravel\Prompts\text;

final class SearchDocsCommand extends Command
{
    public $signature = 'artisense:docs {--search= : Search query for documentation}
                                        {--docVersion= : Version of Laravel documentation to use}
                                        {--limit=3 : Number of results to return}';

    public $description = 'Ask questions about Laravel documentation and get relevant information.';

    private Config $config;

    public function handle(
        ArtisenseRepositoryManager $repositoryManager,
        Config $config,
        Validator $validator,
        VersionManager $versionManager,
    ): int {
        $this->config = $config;

        $flags = [
            'search' => $this->option('search'),
            'limit' => $this->option('limit'),
            'docVersion' => $this->option('docVersion'),
        ];

        $rule = $validator->make($flags, [
            // TODO: Add validation for search terms to include at least a few words
            'limit' => 'nullable|integer|max:10|min:1',
            'docVersion' => ['nullable', Rule::enum(DocumentationVersion::class)],
        ]);

        if ($rule->fails()) {
            $this->error($rule->errors()->first());

            return self::FAILURE;
        }

        $question = $flags['search'] ?? text(
            label: 'What are you looking for?',
            required: true
        );

        if ($flags['docVersion'] !== null) {
            $versionManager->setVersion($flags['docVersion']);
        }

        $repository = $repositoryManager->newConnection();
        $results = $repository->search($question, (int) $flags['limit']);

        if (count($results) === 0) {
            $this->info('No results found for your query.');

            return self::SUCCESS;
        }

        $this->info('🔍 Found relevant information:');
        $this->newLine();

        foreach ($results as $result) {
            /** @var string $title */
            $title = $result->title;

            /** @var string $heading */
            $heading = $result->heading;

            /** @var string $markdown */
            $markdown = $result->markdown;

            /** @var string $link */
            $link = $result->link;

            $this->info("<fg=yellow;options=bold>$title - $heading</>");

            try {
                $formatted = $this->getFormattedOutput($markdown);
                $this->line($formatted);
            } catch (InvalidOutputFormatterException $e) {
                $this->warn('Failed to format markdown with the configured formatter, using basic formatting.');
                $this->warn($e->getMessage());
                $this->outputBasicFormattedMarkdown($markdown);
            }

            $this->info("<fg=blue>Learn more: $link</>");
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function getFormattedOutput(string $markdown): string
    {
        /** @var null|class-string $configuredFormatter */
        $configuredFormatter = $this->config->get('artisense.formatter');

        if ($configuredFormatter === null) {
            return $markdown;
        }

        if (! self::validateFormatter($configuredFormatter)) {
            throw InvalidOutputFormatterException::mustInheritFromOutputFormatter($configuredFormatter);
        }

        /** @var OutputFormatterContract $formatter */
        $formatter = app($configuredFormatter);

        return $formatter->format($markdown);
    }

    /**
     * @param  class-string  $formatter
     *
     * @throws InvalidOutputFormatterException
     */
    private function validateFormatter(string $formatter): bool
    {
        if (! class_exists($formatter)) {
            throw InvalidOutputFormatterException::invalidFormatterClass($formatter);
        }

        return new ReflectionClass($formatter)->implementsInterface(OutputFormatterContract::class);
    }

    /**
     * Format markdown text for terminal output with syntax highlighting.
     */
    private function outputBasicFormattedMarkdown(string $markdown): void
    {
        // Split the markdown into lines for processing
        $lines = explode("\n", $markdown);
        $inCodeBlock = false;
        $inList = false;

        foreach ($lines as $line) {
            // Handle code blocks
            if (str_starts_with(mb_trim($line), '```')) {
                $inCodeBlock = ! $inCodeBlock;
                $this->line($inCodeBlock ? '<fg=cyan>```</>' : '<fg=cyan>```</>');

                continue;
            }

            if ($inCodeBlock) {
                // Format code with cyan color
                $this->line('<fg=cyan>'.$this->escapeAngleBrackets($line).'</>');

                continue;
            }

            // Handle headings (# Heading)
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = mb_strlen($matches[1]);
                $text = $matches[2];

                // Skip h1 headings as they are typically tables of content
                if ($level === 1) {
                    continue;
                }

                // Different colors/styles based on heading level
                match ($level) {
                    2 => $this->line("<fg=magenta;options=bold>## $text</>"),
                    default => $this->line("<fg=magenta>$matches[1] $text</>"),
                };

                continue;
            }

            // Handle inline code (`code`)
            $line = preg_replace_callback('/`([^`]+)`/', fn (array $matches): string => '<fg=cyan>`'.$this->escapeAngleBrackets($matches[1]).'`</>', $line);

            // Handle bold (**bold**)
            $line = preg_replace_callback('/\*\*([^*]+)\*\*/', fn (array $matches): string => '<options=bold>**'.$matches[1].'**</>', (string) $line);

            // Handle lists
            if (preg_match('/^(\s*)([\-\*]|\d+\.)\s+(.+)$/', (string) $line, $matches)) {
                $indent = $matches[1];
                $bullet = $matches[2];
                $text = $matches[3];

                $this->line("$indent<fg=yellow>$bullet</> $text");
                $inList = true;

                continue;
            }
            if ($inList && mb_trim($line ?? '') === '') {
                $inList = false;
            }

            // Handle links [text](url)
            $line = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', fn (array $matches): string => '[<fg=blue>'.$matches[1].'</>](<fg=blue>'.$matches[2].'</>)', (string) $line);

            // Output regular text
            if (mb_trim($line ?? '') !== '') {
                $this->line((string) $line);
            } else {
                $this->line('');
            }
        }
    }

    /**
     * Escape angle brackets to prevent them from being interpreted as console formatting tags.
     */
    private function escapeAngleBrackets(string $text): string
    {
        return str_replace(['<', '>'], ['\\<', '\\>'], $text);
    }
}
