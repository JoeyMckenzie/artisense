<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use League\CommonMark\CommonMarkConverter;

final class ParseDocsCommand extends Command
{
    public $signature = 'artisense:parse-docs';

    public $description = 'Parses and seeds the database with Laravel documentation.';

    public function handle(
        FilesystemManager $storage,
        Files $files,
    ): int {
        $this->info('🔍 Parsing Laravel docs...');

        $disk = $storage->disk('local');
        $docsPath = $disk->path('artisense/docs');
        $dbPath = $disk->path('artisense/artisense.sqlite');

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
        $converter = new CommonMarkConverter();

        $this->line(sprintf('Found %d docs files...', count($docFiles)));

        foreach ($docFiles as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $path = $file->getRelativePathname();
            $raw = $files->get($file->getRealPath());

            // Extract title from the first # line
            preg_match('/^#\s+(.+)/m', $raw, $titleMatch);
            $title = $titleMatch[1] ?? '(Untitled)';

            // Split content by ## subheadings
            $sections = preg_split('/^##\s+/m', $raw);

            // First section (before any ##) – store as intro
            if (! empty($sections[0])) {
                $heading = '(Introduction)';
                $link = str_replace('.md', '', $path).'#'.$this->slugify($heading);

                DB::connection('artisense')->table('docs')->insert([
                    'title' => $title,
                    'heading' => $heading,
                    'content' => strip_tags($converter->convert($sections[0])->getContent()),
                    'path' => $path,
                    'link' => sprintf('https://laravel.com/docs/12.x/%s', $link),
                ]);
            }

            foreach (array_slice($sections, 1) as $section) {
                // Heading is the first line
                $lines = explode("\n", (string) $section, 2);
                $heading = mb_trim($lines[0] ?? '(No heading)');
                $sectionContent = $lines[1] ?? '';
                $link = str_replace('.md', '', $path).'#'.$this->slugify($heading);

                DB::connection('artisense')->table('docs')->insert([
                    'title' => $title,
                    'heading' => $heading,
                    'content' => strip_tags($converter->convert($sectionContent)->getContent()),
                    'path' => $path,
                    'link' => sprintf('https://laravel.com/docs/12.x/%s', $link),
                ]);
            }
        }

        $this->info('✅ Docs parsed and stored!');

        return self::SUCCESS;
    }

    private function slugify(string $text): string
    {
        return mb_strtolower(mb_trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
    }
}
