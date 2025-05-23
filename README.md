<div align="center" style="padding-top: 2rem;">
    <img src="art/logo.png" height="300" width="300" alt="logo"/>
        <img src="https://img.shields.io/packagist/v/joeymckenzie/artisense.svg?style=flat-square" alt="packgist downloads" />
        <img src="https://img.shields.io/github/actions/workflow/status/joeymckenzie/artisense/run-ci.yml?branch=main&label=ci&style=flat-square" alt="ci" />
        <img src="https://img.shields.io/github/actions/workflow/status/joeymckenzie/artisense/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square" alt="packgist downloads" />
        <img src="https://img.shields.io/packagist/dt/joeymckenzie/artisense.svg?style=flat-square" alt="packgist downloads" />
</div>

# Artisense 📕

Laravel docs from the comfort of your terminal.

## Table of Contents

- [Getting started](#getting-started)
- [Usage](#usage)
    - [Flags](#flags)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Credits](#credits)
- [License](#license)

## Getting started

You can install artisense with composer:

```bash
composer require joeymckenzie/artisense
```

You can also publish the config file with:

```bash
php artisan vendor:publish --tag="artisense-config"
```

This will create the following `config/artisense.php` file:

```php
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
```

## Usage

First, prepare artisense by running the install command:

```bash
php artisan artisense:install
```

The install command will do a few things:

1. Download the Laravel markdown documentation files based on the configured version
2. Create a local SQLite database in your project within the storage folder under `storage/artisense`
3. Seed the database with the parsed Laravel documentation

Artisense allows for multiple versions of documentation to coincide with one another. For instance, running the above
command with the default settings will seed documentation within the database for the latest stable version. However,
you may also re-run installation with an updated version value within the `artisense.php`:

```php
return [

    // Other configuration...

    'version' => DocumentationVersion::MASTER,

];
```

You may also install versions by explicitly passing a `--version` flag to the install command:

```php
php artisan artisense:install --version "12.x" // (10.x, 11.x, master, etc.)
```

## Usage

Artisense uses SQLite's full-text search extension [FTS5](https://www.sqlite.org/fts5.html) to store Laravel
documentation.
Once you've successfully installed a version of the documentation using artisense, you may use the `docs` artisense
artisan
command to search relevant sections

```bash
php artisan artisense:docs

 ┌ What are you looking for? ───────────────────────────────────┐
 │ enum validation                                              │
 └──────────────────────────────────────────────────────────────┘
```

If any relevant documentation is found, artisense will display it within your terminal:

> 🔍 Found relevant information:
>
> Validation - enum
> #### enum
>
> The `Enum` rule is a class based rule that validates whether the field under validation contains a valid enum value.
> The `Enum` rule accepts the name of the enum as its only constructor argument. When validating primitive values, a
> backed Enum should be provided to the `Enum` rule:
>
> ```php
> use App\Enums\ServerStatus;
> use Illuminate\Validation\Rule;
> 
> $request->validate([
>     'status' => [Rule::enum(ServerStatus::class)],
> ]);
> The `Enum` rule's `only` and `except` methods may be used to limit which enum cases should be considered valid:
>
> ```php
> Rule::enum(ServerStatus::class)
>     ->only([ServerStatus::Pending, ServerStatus::Active]);
> 
> Rule::enum(ServerStatus::class)
>     ->except([ServerStatus::Pending, ServerStatus::Active]);
> ```
>
> The `when` method may be used to conditionally modify the `Enum` rule:
>
> ```php
> use Illuminate\Support\Facades\Auth;
> use Illuminate\Validation\Rule;
> 
> Rule::enum(ServerStatus::class)
>     ->when(
>         Auth::user()->isAdmin(),
>         fn ($rule) => $rule->only(...),
>         fn ($rule) => $rule->only(...),
>     );
> ```
>
> <a name="rule-exclude"></a>
>
> Learn more: https://laravel.com/docs/12.x/validation#enum

By default, artisense returns the raw markdown it that was used to find the relevant section. A link to the section
within the documentation is also included.

Artisense uses [Laravel Prompts](https://laravel.com/docs/12.x/prompts), though you may also pass a `--search` flag to
the `docs` command:

```bash
php artisan artisense:docs --search "enum validation"
```

Using full-text search, artisense will attempt to find relevant sections, returning **three** entries by default. You
may configure this with the `--limit` flag for the `docs` command:

```bash
php artisan artisense:docs --search "enum validation" --limit 5
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Joey McKenzie](https://github.com/joeymckenzie)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
