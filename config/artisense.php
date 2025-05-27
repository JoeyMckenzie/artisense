<?php

declare(strict_types=1);

use Artisense\Enums\DocumentationVersion;

return [

    /*
    |--------------------------------------------------------------------------
    | Artisense Status
    |--------------------------------------------------------------------------
    |
    | This option controls whether Artisense is enabled for your application.
    | When enabled, Artisense features are available throughout your app.
    | Set this value as false disable Artisense functionality entirely.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Documentation version
    |--------------------------------------------------------------------------
    |
    | Specifies the version of the documentation to use, with both numbered.
    | Versions and master available. By default, the most recent numbered
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
