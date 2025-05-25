<?php

declare(strict_types=1);

namespace Artisense\Actions;

use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\ArtisenseException;
use Artisense\Support\Services\StorageManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use ZipArchive;

final readonly class DownloadDocsAction
{
    public function __construct(
        private Http $http,
        private StorageManager $storage,
    ) {
        //
    }

    /**
     * @throws ArtisenseException|ConnectionException
     */
    public function handle(DocumentationVersion $version): void
    {
        $zipUrl = $version->getZipUrl();
        $response = $this->http->get($zipUrl);

        if (! $response->ok()) {
            $message = sprintf('Failed to download docs from GitHub. Response code: %s', $response->status());
            throw ArtisenseException::from($message);
        }

        $this->storage->ensureDocStorageDirectoryExists();
        $this->storage->put('laravel-docs.zip', $response->body());

        $extractedZipPath = $this->storage->path('laravel-docs.zip');
        $extractPath = $this->storage->getBasePath();
        $this->unzipDocsFile($extractedZipPath, $extractPath);

        $this->storage->delete('laravel-docs.zip');
    }

    /**
     * @throws ArtisenseException
     */
    private function unzipDocsFile(string $extractedZipPath, string $extractPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($extractedZipPath) !== true) {
            throw ArtisenseException::from('Failed to unzip docs.');
        }

        $zip->extractTo($extractPath);
        $zip->close();
    }
}
