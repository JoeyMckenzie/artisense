<?php

declare(strict_types=1);

namespace Artisense;

use Artisense\Contracts\OutputFormatterContract;
use Artisense\Enums\DocumentationVersion;
use Artisense\Enums\SearchPreference;
use Artisense\Exceptions\ArtisenseConfigurationException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Rule;
use ReflectionClass;

/**
 * @internal
 */
final class ArtisenseConfiguration
{
    /** @var DocumentationVersion|DocumentationVersion[] */
    public DocumentationVersion|array $version {
        get {
            return $this->version;
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

    /**
     * @throws ArtisenseConfigurationException
     */
    public function __construct(
        private readonly Config $config,
        private readonly Factory $validator
    ) {
        self::ensureValidConfiguration();
    }

    /**
     * @throws ArtisenseConfigurationException
     */
    private function ensureValidConfiguration(): void
    {
        /** @var array{version: DocumentationVersion, preference: SearchPreference, proximity: int, formatter: class-string} $schema */
        $schema = [
            'version' => $this->config->get('artisense.version'),
            'preference' => $this->config->get('artisense.search.preference'),
            'proximity' => $this->config->get('artisense.search.proximity'),
            'formatter' => $this->config->get('artisense.formatter'),
        ];

        $rules = $this->validator->make($schema, [
            'version' => ['required', $this->mustBeValidDocumentationValue(...)],
            'preference' => ['required', Rule::enum(SearchPreference::class)],
            'proximity' => 'required|integer|min:1|max:50',
            'formatter' => ['nullable', $this->mustImplementOutputFormatter(...)],
        ]);

        if ($rules->fails()) {
            throw ArtisenseConfigurationException::invalidConfiguration($rules->errors()->first());
        }

        /** @var OutputFormatterContract $formatter */
        $formatter = app($schema['formatter']);

        $this->version = $schema['version'];
        $this->formatter = $formatter;
        $this->preference = $schema['preference'];
        $this->proximity = $schema['proximity'];
    }

    private function mustBeValidDocumentationValue(string $attribute, mixed $value, callable $fail): void
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

    private function mustImplementOutputFormatter(string $attribute, mixed $value, callable $fail): void
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
