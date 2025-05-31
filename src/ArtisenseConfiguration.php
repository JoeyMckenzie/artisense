<?php

declare(strict_types=1);

namespace Artisense;

use Artisense\Contracts\OutputFormatterContract;
use Artisense\Enums\DocumentationVersion;
use Artisense\Enums\SearchPreference;
use Artisense\Exceptions\ArtisenseConfigurationException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Validation\Factory as Validator;
use Illuminate\Validation\Rule;
use ReflectionClass;

/**
 * @phpstan-type ConfigurationSchema array{
 *     versions: DocumentationVersion|DocumentationVersion[],
 *     formatter: class-string,
 *     preference: SearchPreference,
 *     proximity: int,
 *     retain_artifacts: ?bool
 * }
 *
 * @internal
 */
final class ArtisenseConfiguration
{
    /** @var DocumentationVersion[] */
    public array $versions {
        get {
            return $this->versions;
        }
    }

    public OutputFormatterContract $formatter {
        get {
            return $this->formatter;
        }
    }

    public SearchPreference $preference {
        get {
            return $this->preference;
        }
    }

    public int $proximity {
        get {
            return $this->proximity;
        }
    }

    public bool $retainArtifacts {
        get {
            return $this->retainArtifacts;
        }
    }

    private function __construct(Config $config)
    {
        /** @var DocumentationVersion|DocumentationVersion[] $version */
        $version = $config->get('artisense.versions');

        /** @var SearchPreference $preference */
        $preference = $config->get('artisense.search.preference');

        /** @var int $proximity */
        $proximity = $config->get('artisense.search.proximity');

        /** @var class-string $formatter */
        $formatter = $config->get('artisense.formatter');

        /** @var bool $retainArtifacts */
        $retainArtifacts = $config->get('artisense.retain_artifacts');

        /** @var OutputFormatterContract $outputter */
        $outputter = app($formatter);

        $this->versions = $version instanceof DocumentationVersion ? [$version] : $version;
        $this->preference = $preference;
        $this->proximity = $proximity;
        $this->formatter = $outputter;
        $this->retainArtifacts = $retainArtifacts;
    }

    /**
     * @throws BindingResolutionException
     * @throws ArtisenseConfigurationException
     */
    public static function init(Application $app): self
    {
        $config = $app->make(Config::class);
        $validator = $app->make(Validator::class);

        self::enforceValidConfiguration($validator, $config);

        return new self($config);
    }

    /**
     * @throws ArtisenseConfigurationException
     */
    private static function enforceValidConfiguration(Validator $validator, Config $config): void
    {
        /** @var ConfigurationSchema $schema */
        $schema = [
            'versions' => $config->get('artisense.versions'),
            'preference' => $config->get('artisense.search.preference'),
            'proximity' => $config->get('artisense.search.proximity'),
            'formatter' => $config->get('artisense.formatter'),
            'retain_artifacts' => $config->get('artisense.retain_artifacts'),
        ];

        $rules = $validator->make($schema, [
            'versions' => ['required', self::mustBeValidDocumentationValue(...)],
            'preference' => ['required', Rule::enum(SearchPreference::class)],
            'proximity' => 'required|integer|min:1|max:50',
            'formatter' => ['nullable', self::mustImplementOutputFormatter(...)],
            'retain_artifacts' => 'required|boolean',
        ]);

        if ($rules->fails()) {
            throw ArtisenseConfigurationException::invalidConfiguration($rules->errors()->first());
        }
    }

    private static function mustBeValidDocumentationValue(string $attribute, mixed $value, callable $fail): void
    {
        if (! is_array($value) && ! $value instanceof DocumentationVersion) {
            $fail("$attribute must be an array or an instance of DocumentationVersion.");

            return;
        }

        if (is_array($value)) {
            $allValuesAreValidVersionEnums = collect($value)->every(fn (mixed $version): bool => $version instanceof DocumentationVersion);
            if (! $allValuesAreValidVersionEnums) {
                $fail('When specifying multiple versions, all must be an instance of DocumentationVersion.');
            }
        }
    }

    private static function mustImplementOutputFormatter(string $attribute, mixed $value, callable $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value)) {
            $fail("$attribute must be a valid class-string.");

            return;
        }

        if (! class_exists($value)) {
            $fail("$value was not found within the project.");

            return;
        }

        $implementsOutputFormatter = new ReflectionClass($value)->implementsInterface(OutputFormatterContract::class);

        if (! $implementsOutputFormatter) {
            $fail("$value must implement OutputFormatterContract.");
        }
    }
}
