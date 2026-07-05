# Changelog

All notable changes to `brightcreations/money-converter` are documented here.

See [docs/ENHANCEMENT_PLAN.md](docs/ENHANCEMENT_PLAN.md) for the joint roadmap with `brightcreations/exchange-rates`.

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
