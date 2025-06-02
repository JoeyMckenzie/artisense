<div align="center" style="padding-top: 2rem;">
    <img src="art/logo.png" height="300" width="300" alt="logo"/>
    <div style="display: inline-block; margin-top: 2rem">
        <img src="https://img.shields.io/packagist/v/joeymckenzie/artisense.svg" alt="packgist downloads" />
        <img src="https://img.shields.io/github/actions/workflow/status/joeymckenzie/artisense/run-ci.yml?branch=main&label=ci" alt="ci" />
        <img src="https://img.shields.io/github/actions/workflow/status/joeymckenzie/artisense/fix-php-code-style-issues.yml?branch=main&label=code%20style" alt="packgist downloads" />
        <img src="https://img.shields.io/packagist/dt/joeymckenzie/artisense.svg" alt="packgist downloads" />
        <img src="https://codecov.io/gh/JoeyMckenzie/artisense/graph/badge.svg?token=AXMP8ZTMKD&style=flat-square" alt="codecov coverage report"/> 
    </div>
</div>

# Artisense 📕

Laravel docs from the comfort of your terminal.

## Table of Contents

- [Motivation](#motivation)
- [How it works](#how-it-works)
- [Requirements](#requirements)
- [Getting started](#getting-started)
- [Usage](#usage)
    - [Versions](#versions)
    - [Formatting](#formatting)
- [Changelog](#changelog)
- [Credits](#credits)
- [License](#license)

## Motivation

Artisense is meant to be a local-first offline copy of the Laravel documentation. Artisense is a set of artisan
commands that allow you to locally store and search the laravel documentation from the comfort of your terminal.

If you're anything like me and living in the terminal, those precious seconds `alt` + `tab`ing between code editor and
browser to review the Laravel documentation really adds up (who has that kinda time?). Why not make docs accessible from
the terminal?

## How it works

At its core, artisense is a SQLite database that lives within your `storage_path()` underneath an `artisense/` folder:

```bash
your-laravel-app/
  app/
  config/
  ...
  storage/
    artisense/
      artisense.sqlite // Where docs are stored
      docs-11.x/ // Documentation markdown files from 11.x
        artisan.md
        ...
      docs-12.x/ // Documentation markdown files from 12.x
        artisan.md
        ...
      docs-master.x/ // Documentation markdown files from master
        artisan.md
        ...
      zips/ // Zip archives of the documentation
        laravel-12.x.zip
        laravel-11.x.zip
        laravel-master.x.zip
        ...
```

Artisense uses SQLite to store documentation pulled from the Laravel
documentation [source](https://github.com/laravel/docs).
The documentation is downloaded as a zip file, extracted into a `artisense/docs-{version}.x/` folder, then processed
into sections to allow full-text search using SQLite's [FTS5](https://www.sqlite.org/fts5.html) extension. Since
artisense is just a SQLite file, you may connect and query it like any other SQLite database.

## Requirements

- Laravel 12 or greater
- PHP 8.4 or greater

## Getting started

Install artisense with composer:

```bash
composer require --dev joeymckenzie/artisense
```

You may also publish the configuration file with:

```bash
php artisan vendor:publish --tag="artisense-config"
```

This will create the following `config/artisense.php` file:

```php
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

    'versions' => DocumentationVersion::VERSION_12,

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

    /*
    |--------------------------------------------------------------------------
    | Retain Artifacts
    |--------------------------------------------------------------------------
    |
    | Specifies the artificat retention policy artisense should use when processing
    | Laravel documentation. If set to true, documentation markdown files along
    | zip archives will be retained within the storage folder during install.
    |
    */

    'retain_artifacts' => true,

];
```

Artisense has a few tuning knobs within its configuration:

- **Version**: specifies the version to run queries against, as multiple versions of the documentation may all be stored
  alongside one another
    - Accepts either a single `DocumentationVersion` or an array of `DocumentationVersions`, e.g.

```php
return [
    // Accepts a singe version
    'versions' => DocumentationVersion::VERSION_12,
    
    // You may also specify multiple versions
    'versions' => [
        DocumentationVersion::VERSION_11,
        DocumentationVersion::VERSION_12,
        DocumentationVersion::MASTER,
    ],
];
```

- **Formatter**: specifies any custom formatting the markdown output should use when results are found
- **Search preference**: SQLite full-text search preference, either `ordered` or `unordered`
- **Search proximity**: relative distance between terms full-text search should consider when querying using an
  `unordered` preference

## Usage

First, prepare artisense by running the install command:

```bash
php artisan artisense:install
```

You'll be prompted with a choice of versions to install:

```bash
🔧 Installing artisense...

 ┌ Which version of documentation would you like to install? ───┐
 │ › ◼ 12.x                                                     │
 │   ◻ 11.x                                                     │
 │   ◻ 10.x                                                     │
 │   ◻ master                                                   │
 └──────────────────────────────────────────────────────────────┘
  You can change the default version within the artisense.php config file.
```

Artisense uses [Laravel Prompts](https://laravel.com/docs/12.x/prompts), so any supported version of the documentation
may be selected. Multiple versions of the documentation may be selected, where each will be processed and stored within
the database allowing for querying across Laravel versions.

The install command will do a few things:

1. Download the Laravel markdown documentation files based on the configured version
2. Create a local SQLite database in your project within the storage folder under `storage/artisense`
3. Seed the database with the processed Laravel documentation

## Usage

Artisense uses SQLite's full-text search extension [FTS5](https://www.sqlite.org/fts5.html) to query Laravel
documentation. Once you've successfully installed a version of the documentation using artisense, you may use the
`artisense:search` artisan command to search relevant sections:

```bash
php artisan artisense:search

 ┌ Enter a search term to find relevant information: ───────────┐
 │ Installing Reverb, handling Stripe webhooks, etc.            │
 └──────────────────────────────────────────────────────────────┘
  Use at least a few characters to get relevant results.
```

If any relevant documentation is found, matches will be displayed in the terminal:

```bash
 ┌ Enter a search term to find relevant information: ───────────────┐
 │ pennant                                                          │
 ├──────────────────────────────────────────────────────────────────┤
 │   2407 - 12.x - Laravel Pennant - Configuration                ┃ │
 │   2406 - 12.x - Laravel Pennant - Installation                 │ │
 │   2433 - 12.x - Laravel Pennant - Store Configuration          │ │
 └──────────────────────────────────────────────────────────────────┘
  Use at least a few characters to get relevant results.
```

and if you select an entry:

> Laravel Pennant - Defining Features Externally - 12.x
> ### Defining Features Externally
>
> If your driver is a wrapper around a third-party feature flag platform, you will likely define features on the
> platform rather than using Pennant's `Feature::define` method. If that is the case, your custom driver should also
> implement the `Laravel\Pennant\Contracts\DefinesFeaturesExternally` interface:
>
> ```
> <?php
> 
> namespace App\Extensions;
> 
> use Laravel\Pennant\Contracts\Driver;
> use Laravel\Pennant\Contracts\DefinesFeaturesExternally;
> 
> class FeatureFlagServiceDriver implements Driver, DefinesFeaturesExternally
> {
>     /**
>      * Get the features defined for the given scope.
>      */
>     public function definedFeaturesForScope(mixed $scope): array {}
> 
>     /* ... */
> }
> ```
>
> The `definedFeaturesForScope` method should return a list of feature names defined for the provided scope.
>
> <a name="events"></a>
>
>
> Learn more: https://laravel.com/docs/12.x/pennant#defining-features-externally

By default, artisense returns the raw markdown from the content that was used to find the relevant section. A link to
the section within the documentation will also included.

### Versions

Within your `artisense.php` configuration file, you may specify one or more versions of Laravel documentation to use
when processes, storing, and searching documentations with artisense commands:

```php
return [

    // Other configurations...
    
    // Specify a version using the `DocumentationVersion` enum
    'versions' => DocumentationVersion::MASTER
    
    // Specify versions using the `DocumentationVersion` enum
    'versions' => [
        DocumentationVersion::VERSION_12,
        DocumentationVersion::VERSION_11,
        DocumentationVersion::MASTER,
    ]
    
]
```

When installing documentation, artisense will default to using the versions specified within your configuration file.
When using the search commands, artisense will also use whichever versions are specified within your configuration to
filter on search results.

For example, if you have versions `12.x`, `11.x`, and master installed, all search results will be returned:

```bash
php artisan artisense:search

 ┌ Enter a search term to find relevant information: ─────────────────┐
 │ validation                                                         │
 ├────────────────────────────────────────────────────────────────────┤
 │   7494 - 11.x - Validation - Accessing Additional Data           ┃ │
 │   11237 - master - Validation - Accessing Additional Data        │ │
 │   3718 - 12.x - Validation - Accessing Additional Data           │ │
 └────────────────────────────────────────────────────────────────────┘
  Use at least a few characters to get relevant results.
```

### Formatting

You may customize the output of the `artisense:search` command through the use of
an [output formatter](https://github.com/JoeyMckenzie/artisense/blob/main/src/Contracts/OutputFormatterContract.php).
The search command will output the raw markdown processed from the identified documentation section, though if an output
formatter is specified within configuration, it will use that format the output.

By default, artisense includes two simple output formatter:

- a [basic](https://github.com/JoeyMckenzie/artisense/blob/main/src/Formatters/BasicMarkdownFormatter.php) markdown
  formatter
- a [glow-based](https://github.com/JoeyMckenzie/artisense/blob/main/src/Formatters/GlowOutputFormatter.php) output
  formatter (requires [glow](https://github.com/charmbracelet/glow) to be installed)

You may specify a custom formatter within your `artisense.php` configuration:

```php
return [

    // Other configuration...

    'formatter' => \App\Support\Formatters\CustomMarkdownFormatter::class,

];
```

You may then implement the `OutputFormatterContract` to format the markdown:

```php
<?php

declare(strict_types=1);

namespace App\Support\Formatters;

use Artisense\Contracts\OutputFormatterContract;

final class TestOutputFormatter implements OutputFormatterContract
{
    public function format(string $markdown): string
    {
        return "FORMATTED: $markdown";
    }
}
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Joey McKenzie](https://github.com/joeymckenzie)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
