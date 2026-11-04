# Changelog

All notable changes to `brightcreations/money-converter` are documented here.

See [docs/ENHANCEMENT_PLAN.md](docs/ENHANCEMENT_PLAN.md) for the joint roadmap with `brightcreations/exchange-rates`.

## [Unreleased]

## [0.14.1] - 2026-11-04

### Changed

- Accept `brightcreations/exchange-rates` `^0.12.0`. 0.14.0 still capped it at `^0.11.0`, so it could not be installed alongside exchange-rates 0.12, the release that marks provisional rates.

## [0.14.0] - 2026-11-04

### Changed

- Fewer provider API calls; conversion results are unchanged:
  - Historical conversions for a date after today (UTC) no longer try to fetch rates from the provider, directly or for the proxy currency. No provider publishes future rates, so these calls always failed; stored rates, interpolation and extrapolation still apply.
  - Within one `convertBulk()` / `convertBulkWithResult()` call, a historical fetch that failed for a currency and date is not retried for later items with the same currency and date.
  - Fresh conversions refresh only the source currency's rates (they read the source → target rate); previously the target currency was fetched too.
  - Bulk conversions with `needsFresh` refresh each source currency once up front and no longer fetch again for every item.
- Provisional rates (period averages such as World Bank's yearly rate; exchange-rates with `CurrencyExchangeRateHistory::isProvisional()`) are a last resort in historical conversions: a provisional exact or fetched rate is kept aside while a daily cross-rate and daily interpolation are tried, and is used before extrapolation. A date that already has a provisional rate is not fetched again (exchange-rates' `exchange-rates:refresh-provisional` retries the daily providers). With older exchange-rates versions nothing changes.
- Extrapolation ignores a provisional neighbour when the other neighbour is a daily rate, matching interpolation, which only uses daily rates as bounds.
- API-fetched historical conversions now report the provider (exchange-rates sets it on fetched rates; it used to be `null`).

## [0.13.0] - 2026-10-27

### Changed

- Support Laravel 13 (`illuminate/*` `^13.0`; needs PHP 8.3+). Laravel 10–12 still supported.
- Accept `brick/math` `^0.13.0` and `^0.14.0` (Laravel 13 needs `^0.14.2`). Still capped below 1.0, which stops accepting floats.
- Accept `brightcreations/exchange-rates` `^0.10.0` and `^0.11.0`. Laravel 13 needs exchange-rates `^0.11.0`.
- CI runs PHP 8.2–8.4 on Laravel 12 and PHP 8.3–8.4 on Laravel 13. `composer.lock` is resolved on PHP 8.2.

## [0.12.0] - 2026-07-05

### Added

- Config-driven `exchange_rates.configurable.rates` for local development via `ExchangeRateProvidersEnum::CONFIGURABLE`.
- `convertBulk()` and `convertBulkWithResult()` for batch conversions.
- `convertWithResult()` returning `MoneyConversionResult` (rate, strategy, provider).
- Bulk historical interpolation prefetch via `getBulkBoundingHistoricalRates` (requires exchange-rates `^0.9.0`).
- `MoneyConverterFetchOnFailTest` covering API fetch fallback when the database has no exact historical rate.
- `MoneyConverterOptions` immutable DTO and `withOptions()` for explicit per-call configuration.
- Config `default_options` for application-wide conversion behaviour (`MoneyConverterOptions::fromConfig()`).
- `?MoneyConverterOptions` on `convertHistorical`, `convertToday`, `convertCurrent`, and `convertFresh`.
- `ConversionPerformed` Laravel event for audit trails (input context + `MoneyConversionResult`; one event per bulk item).
- Built-in `LogConversionPerformed` listener registered via `events.listeners` config.

### Changed

- **Breaking:** `illuminate/support` now requires `^10.0` (Laravel 9 no longer supported).
- **Breaking:** Pin `brightcreations/exchange-rates` to `^0.9.0`; use `ExchangeRate::supportsHistoricalApiFetch()` and `getBulkBoundingHistoricalRates()` directly (removed transitional `method_exists` guards).
- Committed `composer.lock` for reproducible CI installs.

## [0.6.0]

### Added

- Nearest-neighbour **extrapolation** via `extrapolate()` when the target date is before the earliest or after the latest stored historical rate.
- Pest + Testbench test suite with historical extrapolation and bulk fresh-fetch coverage.
- **Historical conversion** section in README (provider matrix, datetime semantics, `interpolate()` / `extrapolate()`).

### Changed

- Fresh rate fetch uses `ExchangeRate::storeBulkExchangeRatesForMultipleCurrencies()` instead of two separate `storeExchangeRates()` calls.
- Expanded docblocks on `extrapolate()`, `fetchOnFail()`, and `convertHistorical()`.

### Fixed

- `RoundingMode::DOWN` constant casing in config and `MoneyConverter` default (was invalid `RoundingMode::Down`).

## [0.5.2]

### Changed

- Pin `brightcreations/exchange-rates` to `^0.8.0` (requires bounding-rate repository methods for interpolation).
- Add explicit `brick/math` dependency (`^0.12.0`) for historical conversion math.

### Fixed

- Correct error message in `convertCurrent()` when a non-conversion exception occurs (no longer refers to fresh exchange rates).

### Removed

- Unused `ExchangeRateServiceInterface` contract (duplicate of exchange-rates APIs; use exchange-rates facades or contracts instead).

## [0.5.1]

### Added

- `interpolate()` on `MoneyConverterInterface` and facade PHPDoc.

## [0.5.0]

### Added

- Historical conversion interpolation via `ExchangeRateRepository::getBoundingHistoricalRates()`.
- Multi-step historical fallback chain (DB lookup, API fetch, proxy currency, interpolation).

## [0.4.x]

### Added

- Configurable `rounding_mode` in `config/money-converter.php`.
- `normalizeRate()` returns `BigDecimal` for consistent historical math.

### Fixed

- Rounding mode constant casing aligned with Brick `RoundingMode` enum.
