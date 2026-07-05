# Money Converter

A Laravel package for converting money between currencies using stored exchange rates and [Brick Money](https://github.com/brick/money).

![Downloads](https://img.shields.io/github/downloads/BrightCreations/money-converter/total)
![License](https://img.shields.io/github/license/BrightCreations/money-converter)
![Last Commit](https://img.shields.io/github/last-commit/BrightCreations/money-converter)
![Stars](https://img.shields.io/github/stars/BrightCreations/money-converter?style=social)

## Overview

`brightcreations/money-converter` converts amounts between currencies in **minor units** (cents). It reads rates from your database (populated by [`brightcreations/exchange-rates`](https://github.com/BrightCreations/exchange-rates)) and supports current, fresh, historical, interpolated, and extrapolated conversions.

## Requirements

| Dependency | Version |
|------------|---------|
| PHP | `>=8.1` |
| Laravel | `10`, `11`, or `12` (database component) |
| [brightcreations/exchange-rates](https://github.com/BrightCreations/exchange-rates) | `^0.9.0` |

## Installation

```bash
composer require brightcreations/money-converter brightcreations/exchange-rates
```

Both packages auto-register their service providers.

## Integration guide

Follow these steps to go from zero to a working conversion.

### 1. Publish exchange-rates config and migrations

```bash
php artisan vendor:publish --tag=exchange-rates-config
php artisan vendor:publish --tag=exchange-rate-migrations
php artisan migrate
```

### 2. Configure exchange-rate API keys

Add provider credentials to `.env`. See the [exchange-rates installation guide](https://github.com/BrightCreations/exchange-rates#-installation) for full details.

```env
EXCHANGE_RATE_API_TOKEN=your_token
OPEN_EXCHANGE_RATE_APP_ID=your_app_id
```

### 3. Publish money-converter config

```bash
php artisan vendor:publish --tag=money-converter-config
```

### 4. Populate exchange rates

Load rates into the database using either approach:

```bash
# Historical backfill (recommended for World Bank or DB-only workflows)
php artisan exchange-rates:backfill
```

```php
// Or fetch current rates for a base currency
use BrightCreations\ExchangeRates\Facades\ExchangeRate;

ExchangeRate::storeExchangeRates('USD');
```

### 5. Convert

```php
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;

$convertedMinor = app(MoneyConverterInterface::class)->convert(10000, 'USD', 'EUR');
// 10000 USD cents (100.00 USD) → EUR cents
```

## Configuration

Published to `config/money-converter.php`.

| Key | Default | Description |
|-----|---------|-------------|
| `default_provider` | PDO builder | Brick `ExchangeRateProvider` used for current/fresh conversion (`ExchangeRateProvidersEnum::PDO`) |
| `proxy_currency_code` | `USD` | Base currency for historical proxy cross-rates when a direct pair is missing |
| `rounding_mode` | `RoundingMode::DOWN` | Brick rounding when converting to minor units |
| `exchange_rates.pdo.table` | `currency_exchange_rates` | Same table written by exchange-rates |
| `exchange_rates.pdo.column` | `exchange_rate` | Rate column |
| `exchange_rates.pdo.source_currency_column` | `base_currency_code` | Source currency column |
| `exchange_rates.pdo.target_currency_column` | `target_currency_code` | Target currency column |
| `exchange_rates.configurable.rates` | see config file | Base → target rate map for local dev (no DB) |

Environment overrides: `EXCHANGE_RATE_DB_TABLE`, `EXCHANGE_RATE_DB_COLUMN`, `EXCHANGE_RATE_DB_SOURCE_CURRENCY_COLUMN`, `EXCHANGE_RATE_DB_TARGET_CURRENCY_COLUMN`.

PDO column names must match the [exchange-rates schema](https://github.com/BrightCreations/exchange-rates).

### Local development without a database

Set `default_provider` to the configurable builder class so Brick reads static rates from config:

```php
use BrightCreations\MoneyConverter\Enums\ExchangeRateProvidersEnum;

'default_provider' => ExchangeRateProvidersEnum::CONFIGURABLE->value,
```

Edit `exchange_rates.configurable.rates` in `config/money-converter.php` to add or change pairs. This provider is intended for local/testing only; production should use PDO with exchange-rates populated data.

## Usage

### Minor units

All amounts are in **minor units** (cents, pence, etc.):

| Input | Meaning |
|-------|---------|
| `10000` with `USD` | 100.00 USD |
| `5000` with `EUR` | 50.00 EUR |

Same-currency conversion returns the input unchanged.

### Basic conversion

```php
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;

$service = app(MoneyConverterInterface::class);

// Current rate (from database via PDO provider)
$eurCents = $service->convert(10000, 'USD', 'EUR');

// Historical rate (pass a date; use startOfDay() for predictable date-only matching)
$eurCents = $service->convert(10000, 'USD', 'EUR', now()->subDays(7)->startOfDay());
```

## Resolving the service

### Constructor injection

```php
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;

class InvoiceService
{
    public function __construct(
        private MoneyConverterInterface $moneyConverter
    ) {}

    public function convertTotal(int $amountCents, string $from, string $to): int
    {
        return $this->moneyConverter->convert($amountCents, $from, $to);
    }
}
```

### Container

```php
$service = resolve(MoneyConverterInterface::class);
// or
$service = app()->make(MoneyConverterInterface::class);
```

### Facade

```php
use BrightCreations\MoneyConverter\Facades\MoneyConverter;

$eurCents = MoneyConverter::convert(10000, 'USD', 'EUR');

// Fluent options (see API reference)
$eurCents = MoneyConverter::fetchOnFail()
    ->interpolate()
    ->convertHistorical(10000, 'USD', 'EUR', now()->subDays(30)->startOfDay());
```

> **Note:** `MoneyConverter` is registered as a **singleton**, but fluent methods (`fetchOnFail()`, `extrapolate()`, `interpolate()`, etc.) return a **new configured instance** and do not mutate the shared singleton. The optional `$on_fail` argument on `convert()` is also scoped to that call only.

## API reference

### Conversion methods

| Method | Description |
|--------|-------------|
| `convert($money, $from, $to, $dateTime = null, $onFail = null, $options = null)` | Routes to current, fresh, or historical conversion based on date and flags |
| `convertCurrent($money, $from, $to, $options = null)` | Uses rates in the database (PDO provider) |
| `convertFresh($money, $from, $to, $options = null)` | Fetches fresh rates via exchange-rates, then converts |
| `convertToday($money, $from, $to, $options = null)` | Historical conversion at today's `startOfDay()` |
| `convertHistorical($money, $from, $to, $dateTime, $options = null)` | Historical fallback chain (see below) |
| `convertWithResult($money, $from, $to, $dateTime = null, $onFail = null, $options = null)` | Same routing as `convert()` but returns `MoneyConversionResult` (amount, rate, strategy, provider) |
| `convertBulk(ConversionItem[] $items, $options = null)` | Batch conversion; returns `int[]` in input order |
| `convertBulkWithResult(ConversionItem[] $items, $options = null)` | Batch conversion with metadata per item |

`ConversionItem` accepts `amount`, `from`, `to`, and optional `date` for historical rows.

### Fluent methods

| Method | Description |
|--------|-------------|
| `extrapolate(bool $enable = true)` | Nearest-neighbour rate when target is outside stored date range; returns a new instance |
| `interpolate(bool $enable = true)` | Linear interpolation between two bounding historical rates; returns a new instance |
| `needsFresh(bool $enable = true)` | Route `convert()` without a date through `convertFresh()`; returns a new instance |
| `throwOnFail()` | Throw on conversion failure (default); returns a new instance |
| `fetchOnFail()` | Fetch rates from API on failure where the provider supports it; returns a new instance |
| `isThrowOnFail()` / `isFetchOnFail()` | Inspect current failure mode |
| `withOptions(MoneyConverterOptions $options)` | Return a new instance with the given options object |

### Options object

For explicit per-call configuration without a fluent chain, pass a `MoneyConverterOptions` instance:

```php
use BrightCreations\MoneyConverter\DTOs\MoneyConverterOptions;

$opts = MoneyConverterOptions::defaults()
    ->withFetchOnFail()
    ->withInterpolate();

$result = $service->convertWithResult(10000, 'USD', 'EUR', $date, options: $opts);
$amounts = $service->convertBulk($items, $opts);
$amount = $service->convertHistorical(10000, 'USD', 'EUR', $date, $opts);
```

Set application-wide defaults in `config/money-converter.php`:

```php
'default_options' => [
    'extrapolate' => false,
    'interpolate' => false,
    'needs_fresh' => false,
    'on_fail' => 'throw', // or 'fetch'
],
```

`MoneyConverter` reads these when resolved from the container. Per-call `$options` or fluent methods override them.

Precedence for `convert()` / `convertWithResult()`:

1. Explicit `$on_fail` argument (when non-null) overrides `options->onFail` for that call
2. `$options` argument (when non-null) overrides instance options
3. Instance options from fluent methods or `withOptions()`
4. Config `default_options` (constructor baseline)

### Audit events

After each successful conversion, the package dispatches `BrightCreations\MoneyConverter\Events\ConversionPerformed` (one event per bulk item). Listeners are registered from `config/money-converter.php`; the default includes `LogConversionPerformed`, which writes an `info` log with input/output amounts, currencies, rate, strategy, and provider.

Disable event dispatch via `.env`:

```env
MONEY_CONVERTER_DISPATCH_CONVERSION_EVENTS=false
```

Configure listeners in `config/money-converter.php` (remove `LogConversionPerformed` to turn off package logging, or add your own):

```php
use BrightCreations\MoneyConverter\Listeners\LogConversionPerformed;

'events' => [
    'listeners' => [
        LogConversionPerformed::class,
        \App\Listeners\PersistConversionAudit::class,
    ],
],
```

Or in your app's `EventServiceProvider`:

```php
protected $listen = [
    \BrightCreations\MoneyConverter\Events\ConversionPerformed::class => [
        \App\Listeners\PersistConversionAudit::class,
    ],
];
```

### Constants

| Constant | Value | Meaning |
|----------|-------|---------|
| `ON_FAIL_THROW_EXCEPTION` | `1` | Throw `MoneyConversionException` on failure |
| `ON_FAIL_FETCH_EXCHANGE_RATES` | `2` | Attempt to fetch rates before failing |

Pass as the fifth argument to `convert()` to temporarily set failure mode:

```php
$service->convert(10000, 'USD', 'EUR', null, MoneyConverterInterface::ON_FAIL_FETCH_EXCHANGE_RATES);
```

## Historical conversion

Historical conversion runs a fallback chain: exact DB lookup → optional API fetch (`fetchOnFail()`) → proxy-currency cross-rate → optional `interpolate()` → optional `extrapolate()`.

### Datetime semantics

- **Exact lookup** (step 1) matches by **date only** (`whereDate`), not time. Pass `startOfDay()` for predictable results.
- **`convertToday()`** already uses `startOfDay()`.
- **Bounding, interpolation, and extrapolation** use full datetime (`<=` / `>=` on `date_time`).

### `fetchOnFail()` and providers

When `fetchOnFail()` is set, historical conversion attempts an API fetch after a DB miss. Behaviour depends on your exchange-rates provider:

| Provider | Auto-fetch on historical miss | Granularity |
|----------|------------------------------|-------------|
| Open Exchange Rates | Yes | Daily |
| Exchange Rate API | Yes | Daily |
| World Bank | No (DB only) | Yearly |

For World Bank or DB-only setups, run `php artisan exchange-rates:backfill` to populate historical data.

### `interpolate()` and `extrapolate()`

```php
use Carbon\Carbon;

// Fill a gap between two stored dates (linear interpolation)
$service->interpolate()->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-01-08'));

// Target after latest stored rate (nearest previous rate)
$service->extrapolate()->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-06-01'));

// Target before earliest stored rate (nearest next rate)
$service->extrapolate()->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-01-01'));
```

- **`interpolate()`** — uses two bounding rates when the target falls strictly between stored dates.
- **`extrapolate()`** — uses the nearest single stored rate when the target is before the earliest or after the latest available date.

## Exceptions

| Exception | When |
|-----------|------|
| `MoneyConversionException` | Conversion fails after all strategies are exhausted, or a non-recoverable error occurs in a convert method |
| `InvalidArgumentException` | Invalid `$on_fail` value passed to `convert()` (must be `ON_FAIL_THROW_EXCEPTION` or `ON_FAIL_FETCH_EXCHANGE_RATES`) |

`MoneyConversionException` wraps the underlying cause when applicable (e.g. missing rate, unsupported historical provider).

## Rate sourcing

money-converter **does not** fetch exchange rates on its own for day-to-day storage — that is handled by [brightcreations/exchange-rates](https://github.com/BrightCreations/exchange-rates). Install and configure exchange-rates to populate `currency_exchange_rates` and `currency_exchange_rates_history`, then use money-converter to convert.

## Contributing

Contributions are welcome. Please open an issue or pull request on GitHub.

## License

MIT License. See [LICENSE](LICENSE).

## Author

[Kareem Mohamed](mailto:kareem.shaaban@brightcreations.com) — Bright Creations
