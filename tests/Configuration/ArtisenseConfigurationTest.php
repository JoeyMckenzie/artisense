<?php

declare(strict_types=1);

namespace Artisense\Tests;

use Artisense\ArtisenseConfiguration;
use Artisense\Contracts\OutputFormatterContract;
use Artisense\Enums\DocumentationVersion;
use Artisense\Enums\SearchPreference;
use Artisense\Exceptions\ArtisenseConfigurationException;
use Artisense\Formatters\BasicMarkdownFormatter;
use Illuminate\Support\Facades\Config;

covers(ArtisenseConfiguration::class);

describe(ArtisenseConfiguration::class, function (): void {
    it('initializes with valid configuration', function (): void {
        // Arrange
        Config::set('artisense.versions', DocumentationVersion::VERSION_12);
        Config::set('artisense.formatter', BasicMarkdownFormatter::class);
        Config::set('artisense.search.preference', SearchPreference::ORDERED);
        Config::set('artisense.search.proximity', 10);
        Config::set('artisense.retain_artifacts', true);

        // Act
        $config = app(ArtisenseConfiguration::class);

        // Assert
        expect($config->versions)->toBe([DocumentationVersion::VERSION_12])
            ->and($config->formatter)->toBeInstanceOf(BasicMarkdownFormatter::class)
            ->and($config->preference)->toBe(SearchPreference::ORDERED)
            ->and($config->proximity)->toBe(10)
            ->and($config->retainArtifacts)->toBe(true);
    });

    it('initializes with multiple versions', function (): void {
        // Arrange
        $versions = [
            DocumentationVersion::VERSION_11,
            DocumentationVersion::VERSION_12,
        ];
        Config::set('artisense.versions', $versions);

        // Act
        $config = app(ArtisenseConfiguration::class);

        // Assert
        expect($config->versions)->toBe($versions);
    });

    it('throws exception for invalid version value', function (): void {
        // Arrange
        Config::set('artisense.versions', 'invalid-version');

        // Act & Assert
        expect(fn () => app(ArtisenseConfiguration::class))
            ->toThrow(ArtisenseConfigurationException::class, 'versions must be an array or an instance of DocumentationVersion.');
    });

    it('throws exception for invalid version array values', function (): void {
        // Arrange
        Config::set('artisense.versions', ['invalid-version-1', 'invalid-version-2']);

        // Act & Assert
        expect(fn () => app(ArtisenseConfiguration::class))
            ->toThrow(ArtisenseConfigurationException::class, 'When specifying multiple versions, all must be an instance of DocumentationVersion.');
    });

    it('throws exception for invalid formatter value', function (): void {
        // Arrange
        Config::set('artisense.formatter', 'NonExistentClass');

        // Act & Assert
        expect(fn () => app(ArtisenseConfiguration::class))
            ->toThrow(ArtisenseConfigurationException::class, 'NonExistentClass was not found within the project.');
    });

    it('throws exception for formatter not implementing OutputFormatterContract', function (): void {
        // Arrange, using a class that exists but doesn't implement OutputFormatterContract
        Config::set('artisense.formatter', self::class);

        // Act & Assert
        expect(fn () => app(ArtisenseConfiguration::class))
            ->toThrow(ArtisenseConfigurationException::class, self::class.' must implement OutputFormatterContract.');
    });

    it('throws exception for invalid preference value', function (): void {
        // Arrange
        Config::set('artisense.search.preference', 'invalid-preference');

        // Act & Assert
        expect(fn () => app(ArtisenseConfiguration::class))
            ->toThrow(ArtisenseConfigurationException::class);
    });

    it('throws exception for invalid proximity value', function (): void {
        // Arrange
        Config::set('artisense.search.proximity', 'not-a-number');

        // Act & Assert
        expect(fn () => app(ArtisenseConfiguration::class))
            ->toThrow(ArtisenseConfigurationException::class);
    });

    it('throws exception for proximity value below minimum', function (): void {
        // Arrange
        Config::set('artisense.search.proximity', 0);

        // Act & Assert
        expect(fn () => app(ArtisenseConfiguration::class))
            ->toThrow(ArtisenseConfigurationException::class);
    });

    it('throws exception for proximity value above maximum', function (): void {
        // Arrange
        Config::set('artisense.search.proximity', 51);

        // Act & Assert
        expect(fn () => app(ArtisenseConfiguration::class))
            ->toThrow(ArtisenseConfigurationException::class);
    });

    it('throws exception for artifcation retention not boolean', function (): void {
        // Arrange
        Config::set('artisense.retain_artifacts', 'yes');

        // Act & Assert
        expect(fn () => app(ArtisenseConfiguration::class))
            ->toThrow(ArtisenseConfigurationException::class);
    });
});
