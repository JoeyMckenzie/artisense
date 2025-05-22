<?php

declare(strict_types=1);

namespace Artisense\Support\Services;

use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\DocumentationVersionException;
use Illuminate\Contracts\Config\Repository;

final readonly class VersionManager
{
    public function __construct(
        private Repository $config
    ) {
        //
    }

    /**
     * @throws DocumentationVersionException
     */
    public function getVersion(): DocumentationVersion
    {
        $value = $this->config->get('artisense.version');

        if ($value instanceof DocumentationVersion) {
            return $value;
        }

        if ($value === null) {
            throw DocumentationVersionException::missingVersion();
        }

        if (! is_string($value)) {
            throw DocumentationVersionException::invalidVersion();
        }

        $version = DocumentationVersion::tryFrom($value);

        if ($version === null) {
            throw DocumentationVersionException::invalidVersion();
        }

        return $version;
    }
}
