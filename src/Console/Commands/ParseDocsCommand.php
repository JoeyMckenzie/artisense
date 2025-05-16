<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Contracts\Support\StorageManager;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

final class ParseDocsCommand extends Command
{
    public $signature = 'artisense:parse-docs';

    public $description = 'Parses and seeds the database with Laravel documentation.';

    private CommonMarkConverter $converter;

    public function handle(
        StorageManager $disk,
        Filesystem $files,
    ): int {
        $this->info('🔍 Parsing Laravel docs...');

        $docsPath = $disk->path('docs');
        $dbPath = $disk->path('artisense.sqlite');

        $this->line('Preparing database...');

        // Ensure DB directory exists
        $files->ensureDirectoryExists(dirname($dbPath));

        if (! $files->exists($dbPath)) {
            $files->put($dbPath, '');
        }

        // Set up SQLite connection
        config([
            'database.connections.artisense' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
            ],
        ]);

        DB::connection('artisense')->statement('DROP TABLE IF EXISTS docs');
        DB::connection('artisense')->statement('CREATE VIRTUAL TABLE docs USING fts5(title, heading, content, path, link)');

        $docFiles = $files->allFiles($docsPath);
        $this->converter = new CommonMarkConverter();

        $this->line(sprintf('Found %d docs files...', count($docFiles)));

        foreach ($docFiles as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $path = $file->getRelativePathname();
            $raw = $files->get($file->getRealPath());

            // Match all headings
            preg_match_all('/^(#{1,6})\s+(.+)$/m', $raw, $matches, PREG_OFFSET_CAPTURE);
            $headings = $matches[0];
            $levels = $matches[1];
            $texts = $matches[2];

            if (empty($headings)) {
                self::createEntry('[Untitled]', '[Intro]', $raw, $path);

                continue;
            }

            $title = null;
            $sections = [];

            for ($i = 0; $i < count($headings); $i++) {
                // Start of current heading
                /** @var int $currentHeading */
                $currentHeading = $headings[$i][1];

                // Start of next heading or end of document
                $nextHeading = $headings[$i + 1][1] ?? mb_strlen($raw);

                // Actual heading text (e.g., "Available Rules")
                $headingText = mb_trim($texts[$i][0]);

                // How many # characters (1 for h1, 2 for h2, etc.)
                $level = mb_strlen($levels[$i][0]);

                // Markdown section starting at current heading and ending at next
                $content = mb_substr($raw, $currentHeading, $nextHeading - $currentHeading);

                // Capture the first h1 as the document title
                if ($level === 1 && $title === null) {
                    $title = $headingText;
                }

                // Save this section for later DB insert
                $sections[] = [
                    'heading' => $headingText,
                    'content' => $content,
                ];
            }

            $title ??= '[Untitled]';

            foreach ($sections as $section) {
                self::createEntry($title, $section['heading'], $section['content'], $path);
            }
        }

        $this->info('✅ Docs parsed and stored!');

        return self::SUCCESS;
    }

    private function createEntry(string $title, string $heading, string $content, string $path): void
    {
        $link = str_replace('.md', '', $path).'#'.$this->slugify($heading);
        $baseUrl = Config::string('artisense.base_url');

        if (! Str::endsWith($baseUrl, '/')) {
            $baseUrl = sprintf('%s/', $baseUrl);
        }

        DB::connection('artisense')->table('docs')->insert([
            'title' => $title,
            'heading' => $heading,
            'content' => strip_tags($this->converter->convert($content)->getContent()),
            'path' => $path,
            'link' => sprintf('%s%s', $baseUrl, $link),
        ]);
    }

    private function slugify(string $text): string
    {
        $slugged = preg_replace('/[^a-z0-9]+/i', '-', $text);
        assert(is_string($slugged));

        return mb_strtolower(mb_trim($slugged, '-'));
    }

    private function createEntryForDocumentSection(string $section, string $title, string $path): void
    {
        $lines = explode("\n", $section, 2);
        $heading = mb_trim($lines[0]);
        $content = $lines[1] ?? '';
        self::createEntry($title, $heading, $content, $path);
    }
}
