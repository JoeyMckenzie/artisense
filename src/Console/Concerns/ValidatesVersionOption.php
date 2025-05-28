<?php

declare(strict_types=1);

namespace Artisense\Console\Concerns;

use Artisense\Enums\DocumentationVersion;
use Artisense\Exceptions\DocumentationVersionException;
use Artisense\Support\Services\VersionManager;

trait ValidatesVersionOption
{
    /**
     * @throws DocumentationVersionException
     */
    public function getVersion(VersionManager $versionManager): DocumentationVersion
    {
        $versionOption = $this->option('docVersion');

        if (is_string($versionOption) && $versionOption !== '') {
            $version = DocumentationVersion::tryFrom($versionOption);

            if ($version === null) {
                throw DocumentationVersionException::invalidVersion();
            }

            $versionManager->setVersion($version);
        }

        return $versionManager->getVersion();
    }
}
