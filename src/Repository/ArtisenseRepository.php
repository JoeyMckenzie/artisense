<?php

declare(strict_types=1);

namespace Artisense\Repository;

use Artisense\Enums\DocumentationVersion;
use Artisense\Support\Services\VersionManager;
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
        $exists = $this->db->selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name='docs'");

        if ($exists === null) {
            $this->db->statement('CREATE VIRTUAL TABLE docs USING fts5(title, heading, markdown, content, path, version, link)');
        }
    }

    public function deleteExistingEntries(): void
    {
        $this->db->statement('DELETE FROM docs WHERE version = ?', [$this->version->value]);
    }

    public function createEntry(
        string $title,
        string $heading,
        string $markdown,
        string $content,
        string $path,
        string $link,
        ?DocumentationVersion $version = null,
    ): void {
        $this->db->table('docs')->insert([
            'title' => $title,
            'heading' => $heading,
            'markdown' => $markdown,
            'content' => $content,
            'path' => $path,
            'version' => $version !== null ? $version->value : $this->version->value,
            'link' => sprintf('%s%s', $this->baseUrl, $link),
        ]);
    }

    /**
     * @return stdClass[]
     */
    public function search(string $query, int $limit = 5, ?DocumentationVersion $version = null): array
    {
        return $this->db->table('docs')
            ->whereRaw('content MATCH ?', [$query])
            ->whereRaw('heading != title') // Exclude h1 headings (where heading equals title)
            ->where('version', $version !== null ? $version->value : $this->version->value)
            ->orderByRaw('rank')
            ->limit($limit)
            ->get(['title', 'heading', 'markdown', 'link'])
            ->all();
    }
}
