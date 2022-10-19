# Anadolu Ajansı Api

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kitlesoft/aa.svg?style=flat-square)](https://packagist.org/packages/kitlesoft/aa)
[![Tests](https://github.com/kitlesoft/aa/actions/workflows/run-tests.yml/badge.svg?branch=main)](https://github.com/kitlesoft/aa/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/kitlesoft/aa.svg?style=flat-square)](https://packagist.org/packages/kitlesoft/aa)

Anadolu Ajansı Api


## Installation

You can install the package via composer:

```bash
composer require kitlesoft/aa
```

## Usage

```php
$aaApi = new Kitlesoft\Aa\Api([
            'username' => 'xxxxxxx',
            'password' => 'xxxxxxx',
            'mediaFormat' => 'web',
            'summaryLength' => 120,
            'summaryDot' => false,
        ]);
echo $aaApi->documentList($aaApi->search([
            'filter_type' => 1,
            'filter_language' => 1,
            'filter_category' => null,
            'limit' => 20,
        ]));
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Kitlesoft](https://github.com/Kitlesoft)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
