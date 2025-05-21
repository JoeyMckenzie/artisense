<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Repository\ArtisenseRepository;
use Artisense\Repository\ArtisenseRepositoryManager;
use Artisense\Support\DiskManager;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Finder\SplFileInfo;

final class SeedDocsCommand extends Command
{
    public $signature = 'artisense:seed-docs';

    public $description = 'Parses and seeds the database with Laravel documentation.';

    private CommonMarkConverter $converter;

    private Filesystem $files;

    private ArtisenseRepository $repository;

    public function handle(
        Filesystem $files,
        DiskManager $disk,
        ArtisenseRepositoryManager $repositoryManager,
    ): int {
        $this->line('🔍 Preparing database...');

        $docsPath = $disk->path('docs');
        $this->files = $files;
        $this->repository = $repositoryManager->newConnection();
        $this->repository->createDocsTable();
        $this->converter = new CommonMarkConverter();

        // Only care about markdown files for now, no need to process anything else
        $docFiles = collect($this->files->allFiles($docsPath))
            ->filter(fn (SplFileInfo $file) => $file->isFile())
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'md');

        $this->line(sprintf('Found %d docs files...', count($docFiles)));

        foreach ($docFiles as $file) {
            self::processMarkdownDocument($file);
        }

        $this->info('✅ Docs parsed and stored!');

        return self::SUCCESS;
    }

    private function processMarkdownDocument(SplFileInfo $file): void
    {
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

    private function createEntry(string $title, string $heading, string $markdown, string $path): void
    {
        $link = sprintf('%s#%s', str_replace('.md', '', $path), $this->slugify($heading));
        $content = strip_tags($this->converter->convert($markdown)->getContent());
        $this->repository->createEntry($title, $heading, $markdown, $content, $path, $link);
    }

    private function slugify(string $text): string
    {
        $slugged = Str::slug($text);

        return mb_strtolower(mb_trim($slugged, '-'));
    }
}
