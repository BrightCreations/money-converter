# Changelog

All notable changes to `brightcreations/money-converter` are documented here.

See [docs/ENHANCEMENT_PLAN.md](docs/ENHANCEMENT_PLAN.md) for the joint roadmap with `brightcreations/exchange-rates`.

## [Unreleased]

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
