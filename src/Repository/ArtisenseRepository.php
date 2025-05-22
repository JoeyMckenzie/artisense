<?php

declare(strict_types=1);

namespace Artisense\Repository;

use Artisense\Enums\DocumentationVersion;
use Artisense\Support\VersionManager;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class ArtisenseRepository
{
    private DocumentationVersion $version;

    private string $baseUrl;

    public function __construct(
        private ConnectionInterface $db,
        private VersionManager $versionManager,
    ) {
        $this->version = $this->versionManager->getVersion();
        $this->baseUrl = $this->version->getDocumentationBaseUrl();
    }

    public function createDocsTable(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS docs');
        $this->db->statement('CREATE VIRTUAL TABLE docs USING fts5(title, heading, markdown, content, path, version, link)');
    }

    public function createEntry(
        string $title,
        string $heading,
        string $markdown,
        string $content,
        string $path,
        string $link,
    ): void {
        $this->db->table('docs')->insert([
            'title' => $title,
            'heading' => $heading,
            'markdown' => $markdown,
            'content' => $content,
            'path' => $path,
            'version' => $this->version->value,
            'link' => sprintf('%s%s', $this->baseUrl, $link),
        ]);
    }

    /**
     * Search the documentation using full-text search.
     * Excludes h1 headings (where heading equals title) as these are typically tables of content.
     *
     * @return stdClass[] The search results
     */
    public function search(string $query, int $limit = 5): array
    {
        return $this->db->table('docs')
            ->whereRaw('content MATCH ?', [$query])
            ->whereRaw('heading != title') // Exclude h1 headings (where heading equals title)
            ->orderByRaw('rank')
            ->limit($limit)
            ->get(['title', 'heading', 'markdown', 'link'])
            ->all();
    }
}
