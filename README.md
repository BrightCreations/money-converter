## Money Converter Package
A PHP package for converting money between different currencies.

![Downloads](https://img.shields.io/github/downloads/BrightCreations/money-converter/total)
![License](https://img.shields.io/github/license/BrightCreations/money-converter)
![Last Commit](https://img.shields.io/github/last-commit/BrightCreations/money-converter)
![Stars](https://img.shields.io/github/stars/BrightCreations/money-converter?style=social)

## Overview
This package provides a simple and efficient way to convert money between different currencies. It uses a unified interface to fetch exchange rates and perform conversions, making it easy to integrate with various exchange rate services.

## Features
- Converts money between different currencies
- Uses a unified interface to fetch exchange rates and perform conversions
- Supports multiple exchange rate services
- Throws exceptions for invalid conversions or missing exchange rates
- Easy to integrate with various frameworks and applications

## Installation
To install the Exchange Rates Service package, run the following command in your terminal:

```bash
composer require brightcreations/money-converter
```

## Configuration
To configure the package, publish the configuration file using the following command:

```bash
php artisan vendor:publish --provider="BrightCreations\MoneyConverter\MoneyConverterServiceProvider"
```

Then, update the `money-converter.php` configuration file to suit your needs.

## Usage
To retrieve exchange rates, use the `MoneyConverterInterface`:

```php
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;

// Converts 100.00 USD to EUR using the current exchange rate
$convertedMinorInt = $service->convert(10000, 'USD', 'EUR');

// Converts 100.00 USD to EUR using the exchange rate of a previous date
$convertedMinorInt = $service->convert(10000, 'USD', 'EUR', now()->subDays(1));
```

> **Important Note:**
> Before using the package, make sure that the tables of the exchange rates are in your database and the names of the tables and columns are reflected in the config as it is.

You can inject the service into a constructor or resolve it using the `resolve` or `app->make` method. Here are examples of each approach:

### Constructor Injection

```php
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;

class SomeClass {
    private $service;

    public function __construct(MoneyConverterInterface $service) {
        $this->service = $service;
    }

    public function someMethod() {
        $convertedMinorInt = $this->service->convert(10000, 'USD', 'EUR');
    }
}
```

### Using `resolve` Method

```php
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;

$service = resolve(MoneyConverterInterface::class);
$convertedMinorInt = $service->convert(10000, 'USD', 'EUR');
```

### Using `app->make` Method

```php
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;

$service = app()->make(MoneyConverterInterface::class);
$convertedMinorInt = $service->convert(10000, 'USD', 'EUR');
```

## Historical conversion

Historical conversion runs a fallback chain: exact DB lookup, optional API fetch (`fetchOnFail()`), proxy-currency cross-rate, then optional `interpolate()` and `extrapolate()`.

### Datetime semantics

- **Exact lookup** (step 1) matches by **date only** (`whereDate`), not time. Pass `startOfDay()` for predictable results, e.g. `$service->convert(10000, 'USD', 'EUR', now()->subDays(1)->startOfDay())`.
- **`convertToday()`** already uses `startOfDay()`.
- **Bounding, interpolation, and extrapolation** use full datetime (`<=` / `>=` on `date_time`).

### `fetchOnFail()` and providers

When `fetchOnFail()` is set, historical conversion attempts an API fetch after a DB miss. Behaviour depends on your [exchange-rates](https://github.com/BrightCreations/exchange-rates) provider:

| Provider | Auto-fetch on historical miss | Granularity |
|----------|------------------------------|-------------|
| Open Exchange Rates | Yes | Daily |
| Exchange Rate API | Yes | Daily |
| World Bank | No (DB only) | Yearly |

For World Bank or DB-only setups, run `php artisan exchange-rates:backfill` to populate historical data.

### `interpolate()` and `extrapolate()`

```php
// Fill a gap between two stored dates (linear interpolation)
$service->interpolate()->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-01-08'));

// Target after latest stored rate (nearest previous rate)
$service->extrapolate()->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-06-01'));

// Target before earliest stored rate (nearest next rate)
$service->extrapolate()->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-01-01'));
```

- **`interpolate()`** — uses two bounding rates when the target falls strictly between stored dates.
- **`extrapolate()`** — uses the nearest single stored rate when the target is before the earliest or after the latest available date.

## API Documentation
Coming soon...

## Contributing
Contributions are welcome! Please submit a pull request or open an issue to report any bugs or suggest new features.

## License
This package is licensed under the MIT License.

## Author
Kareem Mohamed - Bright Creations
Email: [kareem.shaaban@brightcreations.com](mailto:kareem.shaaban@brightcreations.com)

## Version
0.1.2
