<?php

declare(strict_types=1);

use Artisense\Enums\DocumentationVersion;
use Artisense\Enums\SearchPreference;

return [

    /*
    |--------------------------------------------------------------------------
    | Documentation version
    |--------------------------------------------------------------------------
    |
    | Specifies the version of the documentation to use, with both numbered
    | versions and master available. By default, the most recent numbered
    | is used if no version is specified while attempting to download.
    |
    */

    'version' => DocumentationVersion::VERSION_12,

    /*
    |--------------------------------------------------------------------------
    | Output Formatter
    |--------------------------------------------------------------------------
    |
    | Specifies the optional formatter that the output should use for markdown.
    | Markdown content will be returned from artisense and can be formatted
    | Using any formatting tools installed wherever artisense is running.
    |
    */

    'formatter' => null,

    /*
    |--------------------------------------------------------------------------
    | Search Preference
    |--------------------------------------------------------------------------
    |
    | Specifies the search preference to use when querying for documentation.
    | Order searches are used by default, returning results using ordered
    | phrase matching. You may choose your preference to adjust results.
    |
    */

    'search' => [
        'preference' => SearchPreference::ORDERED,
        'proximity' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Open AI Integration
    |--------------------------------------------------------------------------
    |
    | Specifies the Open AI API key and organization for an enhanced artisense
    | experience. If enabled, artisense will use embeddings to search docs
    | rather than full-text search with SQLite that is enabled by default.
    |
    */

    'openai_api_key' => null,
    'openai_chunk_size' => 100,

];
