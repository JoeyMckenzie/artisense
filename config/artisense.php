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

];
