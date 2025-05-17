<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Contracts\Support\StorageManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Finder\SplFileInfo;

final class ParseDocsCommand extends Command
{
    public $signature = 'artisense:parse-docs';

    public $description = 'Parses and seeds the database with Laravel documentation.';

    private CommonMarkConverter $converter;

    private ConnectionInterface $db;

    private Filesystem $files;

    public function handle(
        StorageManager $disk,
        Filesystem $files,
        Repository $config,
        ConnectionResolverInterface $resolver
    ): int {
        $this->info('🔍 Parsing Laravel docs...');

        $docsPath = $disk->path('docs');
        $dbPath = $disk->path('artisense.sqlite');

        $this->line('Preparing database...');

        // Ensure DB directory exists
        $this->files = $files;
        $this->files->ensureDirectoryExists(dirname($dbPath));

        if (! $this->files->exists($dbPath)) {
            $this->files->put($dbPath, '');
        }

        // Set up SQLite connection
        $config->set([
            'database.connections.artisense' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
            ],
        ]);

        $this->db = $resolver->connection('artisense');
        $this->db->statement('DROP TABLE IF EXISTS docs');
        $this->db->statement('CREATE VIRTUAL TABLE docs USING fts5(title, heading, markdown, content, path, link)');

        $docFiles = $this->files->allFiles($docsPath);
        $this->converter = new CommonMarkConverter();

        $this->line(sprintf('Found %d docs files...', count($docFiles)));

        foreach ($docFiles as $file) {
            self::processMarkdownDocument($file);
        }

        $this->info('✅ Docs parsed and stored!');

        return self::SUCCESS;
    }

    private function processMarkdownDocument(SplFileInfo $file): void
    {
        if ($file->getExtension() !== 'md') {
            return;
        }

        $path = $file->getRelativePathname();
        $raw = $this->files->get($file->getRealPath());

        // Match all headings
        preg_match_all('/^(#{1,6})\s+(.+)$/m', $raw, $matches, PREG_OFFSET_CAPTURE);
        $headings = $matches[0];
        $levels = $matches[1];
        $texts = $matches[2];

        if (empty($headings)) {
            self::createEntry('[Untitled]', '[Intro]', $raw, $path);

            return;
        }

        $title = null;
        $sections = [];

        for ($i = 0; $i < count($headings); $i++) {
            // Start of current heading
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

    private function createEntry(string $title, string $heading, string $content, string $path): void
    {
        $link = sprintf('%s#%s', str_replace('.md', '', $path), $this->slugify($heading));
        $baseUrl = Config::string('artisense.base_url');

        if (! Str::endsWith($baseUrl, '/')) {
            $baseUrl = sprintf('%s/', $baseUrl);
        }

        $this->db->table('docs')->insert([
            'title' => $title,
            'heading' => $heading,
            'markdown' => $content,
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
}
