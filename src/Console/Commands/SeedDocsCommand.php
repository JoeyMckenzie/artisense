<?php

declare(strict_types=1);

namespace Artisense\Console\Commands;

use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\DocumentationVersionException;
use Artisense\Repository\ArtisenseRepository;
use Artisense\Repository\ArtisenseRepositoryManager;
use Artisense\Support\Services\StorageManager;
use Artisense\Support\Services\VersionManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Finder\SplFileInfo;

final class SeedDocsCommand extends Command
{
    public $signature = 'artisense:seed-docs {--docVersion= : Version of Laravel documentation to use}';

    public $description = 'Parses and seeds the database with Laravel documentation.';

    private CommonMarkConverter $converter;

    private Filesystem $files;

    private ArtisenseRepository $repository;

    private ?DocumentationVersion $version = null;

    public function handle(
        Filesystem $files,
        StorageManager $disk,
        ArtisenseRepositoryManager $repositoryManager,
        VersionManager $versionManager,
        Factory $validator
    ): int {
        /** @var array{docVersion: ?string} $flags */
        $flags = [
            'docVersion' => $this->option('docVersion'),
        ];

        $rule = $validator->make($flags, [
            'docVersion' => ['nullable', Rule::enum(DocumentationVersion::class)],
        ]);

        if ($rule->fails()) {
            $this->error($rule->errors()->first());

            return self::FAILURE;
        }

        if ($flags['docVersion'] !== null) {
            $versionManager->setVersion((string) $flags['docVersion']);
        }

        $this->line('🔍 Preparing database...');

        try {
            $this->version = $versionManager->getVersion();
        } catch (DocumentationVersionException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $docsPath = $disk->path($this->version->getExtractedFolderName());
        $this->files = $files;

        if (! $this->files->isDirectory($docsPath)) {
            $this->error(sprintf('Documentation for version "%s" does not exist, please first run the download command.', $this->version->value));

            return self::FAILURE;
        }

        $this->repository = $repositoryManager->newConnection();
        $this->repository->createDocsTable();
        $this->converter = new CommonMarkConverter();

        // Only care about markdown files for now, no need to process anything else
        $docFiles = collect($this->files->allFiles($docsPath))
            ->filter(fn (SplFileInfo $file) => $file->isFile())
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'md');

        $this->line(sprintf('Found %d docs files...', count($docFiles)));

        // Need to avoid doc duplicates, so delete all entries for the configured version before seeding
        $this->repository->deleteExistingEntries();

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

        /** @var list<list<array{string, int<-1, max>}>> $matches */
        $matches = [];

        preg_match_all('/^(#{1,6})\s+(.+)$/m', $raw, $matches, PREG_OFFSET_CAPTURE);

        /** @var list<array{string, int<-1, max>}> $headings */
        $headings = $matches[0];

        /** @var list<array{non-empty-string, int<-1, max>}> $levels */
        $levels = $matches[1];

        /** @var list<array{non-empty-string, int<-1, max>}> $texts */
        $texts = $matches[2];

        if (empty($headings)) {
            self::createEntry('[Untitled]', '[Intro]', $raw, $path);

            return;
        }

        $title = null;

        /** @var array<int, array{heading: string, content: string}> $sections */
        $sections = [];

        for ($i = 0; $i < count($headings); $i++) {
            $sections[] = self::parseSection($i, $headings, $levels, $texts, $raw, $title);
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

    /**
     * @param  list<array{string, int<-1, max>}>  $headings
     * @param  list<array{non-empty-string, int<-1, max>}>  $levels
     * @param  list<array{non-empty-string, int<-1, max>}>  $texts
     * @return array{heading: string, content: string}
     */
    private function parseSection(int $index, array $headings, array $levels, array $texts, string $raw, ?string &$title): array
    {
        // Start of current heading
        $currentHeading = $headings[$index][1];

        // Start of next heading or end of document
        $nextHeading = $headings[$index + 1][1] ?? mb_strlen($raw);

        // Actual heading text (e.g., "Available Rules")
        $headingText = mb_trim($texts[$index][0]);

        // How many # characters (1 for h1, 2 for h2, etc.)
        $level = mb_strlen($levels[$index][0]);

        // Markdown section starting at current heading and ending at next
        $content = mb_substr($raw, $currentHeading, $nextHeading - $currentHeading);

        // Capture the first h1 as the document title
        if ($level === 1 && $title === null) {
            $title = $headingText;
        }

        // Save this section for later DB insert
        return [
            'heading' => $headingText,
            'content' => $content,
        ];
    }
}
