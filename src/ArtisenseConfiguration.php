<?php

declare(strict_types=1);

namespace Artisense;

use Artisense\Contracts\OutputFormatterContract;
use Artisense\Enums\DocumentationVersion;
use Artisense\Enums\SearchPreference;
use Artisense\Exceptions\ArtisenseConfigurationException;
use Artisense\Exceptions\InvalidOutputFormatterException;
use Artisense\Support\Formatters\BasicMarkdownFormatter;
use Illuminate\Contracts\Config\Repository as Config;
use ReflectionClass;

final readonly class ArtisenseConfiguration
{
    public function __construct(
        private Config $config
    ) {
        //
    }

    /**
     * @throws InvalidOutputFormatterException
     */
    public function getOutputFormatter(): OutputFormatterContract
    {
        /** @var null|class-string $configuredFormatter */
        $configuredFormatter = $this->config->get('artisense.formatter');

        if ($configuredFormatter === null) {
            $configuredFormatter = BasicMarkdownFormatter::class;
        } elseif (! self::validateFormatter($configuredFormatter)) {
            throw InvalidOutputFormatterException::mustInheritFromOutputFormatter($configuredFormatter);
        }

        /** @var OutputFormatterContract $formatter */
        $formatter = app($configuredFormatter);

        return $formatter;
    }

    /**
     * @throws ArtisenseConfigurationException
     */
    public function getVersion(): DocumentationVersion
    {
        $value = $this->config->get('artisense.version');

        if ($value instanceof DocumentationVersion) {
            return $value;
        }

        if ($value === null) {
            throw ArtisenseConfigurationException::missingVersion();
        }

        if (! is_string($value)) {
            throw ArtisenseConfigurationException::invalidVersion();
        }

        $version = DocumentationVersion::tryFrom($value);

        if ($version === null) {
            throw ArtisenseConfigurationException::invalidVersion();
        }

        return $version;
    }

    /**
     * @throws ArtisenseConfigurationException
     */
    public function getSearchPreference(): SearchPreference
    {

        $value = $this->config->get('artisense.search.preference');

        if ($value instanceof SearchPreference) {
            return $value;
        }

        if ($value === null) {
            throw ArtisenseConfigurationException::missingPreference();
        }

        if (! is_string($value)) {
            throw ArtisenseConfigurationException::invalidPreference();
        }

        $preference = SearchPreference::tryFrom($value);

        if ($preference === null) {
            throw ArtisenseConfigurationException::invalidPreference();
        }

        return $preference;
    }

    /**
     * @throws ArtisenseConfigurationException
     */
    public function getSearchProximity(): int
    {
        $value = $this->config->get('artisense.search.proximity', 10);

        if (! is_int($value) || $value < 1) {
            throw ArtisenseConfigurationException::invalidProximity();
        }

        return $value;
    }

    /**
     * @param  class-string  $formatter
     *
     * @throws InvalidOutputFormatterException
     */
    private function validateFormatter(string $formatter): bool
    {
        if (! class_exists($formatter)) {
            throw InvalidOutputFormatterException::invalidFormatterClass($formatter);
        }

        return new ReflectionClass($formatter)->implementsInterface(OutputFormatterContract::class);
    }
}
