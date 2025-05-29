<?php

declare(strict_types=1);

namespace Artisense\Formatters;

use Artisense\Contracts\OutputFormatterContract;
use Illuminate\Support\Str;

final class BasicMarkdownFormatter implements OutputFormatterContract
{
    public function format(string $markdown): string
    {
        // Split the markdown into lines for processing
        $lines = explode("\n", $markdown);
        $inCodeBlock = false;
        $inList = false;
        $output = Str::of('');

        foreach ($lines as $line) {
            // Handle code blocks
            if (str_starts_with(mb_trim($line), '```')) {
                $inCodeBlock = ! $inCodeBlock;
                $output = $output->append($inCodeBlock ? '<fg=cyan>```</>' : '<fg=cyan>```</>')->newLine();

                continue;
            }

            if ($inCodeBlock) {
                // Format code with cyan color
                $output = $output->append('<fg=cyan>'.$this->escapeAngleBrackets($line).'</>')->newLine();

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
                $output = match ($level) {
                    2 => $output->append("<fg=magenta;options=bold>## $text</>")->newLine(),
                    default => $output->append("<fg=magenta>$matches[1] $text</>")->newLine(),
                };

                continue;
            }

            // Handle inline code (`code`)
            $line = preg_replace_callback('/`([^`]+)`/', fn (array $matches): string => '<fg=cyan>`'.$this->escapeAngleBrackets($matches[1]).'`</>', $line);

            // Handle bold (**bold**)
            $line = preg_replace_callback('/\*\*([^*]+)\*\*/', fn (array $matches): string => '<options=bold>**'.$matches[1].'**</>', (string) $line);

            // Handle lists
            if (preg_match('/^(\s*)([\-*]|\d+\.)\s+(.+)$/', (string) $line, $matches)) {
                $indent = $matches[1];
                $bullet = $matches[2];
                $text = $matches[3];

                $output = $output->append("$indent<fg=yellow>$bullet</> $text")->newLine();
                $inList = true;

                continue;
            }
            if ($inList && mb_trim($line ?? '') === '') {
                $inList = false;
            }

            // Handle links [text](url)
            $line = preg_replace_callback('/\[([^]]+)]\(([^)]+)\)/', fn (array $matches): string => '[<fg=blue>'.$matches[1].'</>](<fg=blue>'.$matches[2].'</>)', (string) $line);

            // Output regular text
            if (mb_trim($line ?? '') !== '') {
                $output = $output->append((string) $line)->newLine();
            } else {
                $output = $output->append('')->newLine();
            }
        }

        return $output->toString();
    }

    /**
     * Escape angle brackets to prevent them from being interpreted as console formatting tags.
     */
    private function escapeAngleBrackets(string $text): string
    {
        return str_replace(['<', '>'], ['\\<', '\\>'], $text);
    }
}
