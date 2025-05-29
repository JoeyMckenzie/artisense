<?php

declare(strict_types=1);

use Artisense\Enums\DocumentationVersion;
use Artisense\Enums\SearchPreference;
use Artisense\Formatters\BasicMarkdownFormatter;

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

    'formatter' => BasicMarkdownFormatter::class,

    /*
    |--------------------------------------------------------------------------
    | Search Preference
    |--------------------------------------------------------------------------
    |
    | Specifies the search preferences to use when querying for documentation.
    | Ordered searches are used by default, returning results using ordered
    | phrase matching. You may choose your preference to adjust results.
    |
    */

    'search' => [
        'preference' => SearchPreference::ORDERED,
        'proximity' => 10,
    ],

];
