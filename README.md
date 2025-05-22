<div align="center" style="padding-top: 2rem;">
    <img src="art/logo.png" height="400" width="400" alt="logo"/>
</div>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/artisense/artisense.svg?style=flat-square)](https://packagist.org/packages/artisense/artisense)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/artisense/artisense/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/artisense/artisense/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/artisense/artisense/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/artisense/artisense/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/artisense/artisense.svg?style=flat-square)](https://packagist.org/packages/artisense/artisense)

Laravel docs from the comfort of your terminal.

## Installation

You can install the package via composer:

```bash
composer require joeymckenzie/artisense
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="artisense-config"
```

The contents of the published config file are:

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

```bash
$ php artisan artisense:docs --query "install reverb"
```

## Testing

```bash
composer run test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Joey McKenzie](https://github.com/joeymckenzie)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
