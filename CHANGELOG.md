# Changelog

All notable changes to `brightcreations/money-converter` are documented here.

See [docs/ENHANCEMENT_PLAN.md](docs/ENHANCEMENT_PLAN.md) for the joint roadmap with `brightcreations/exchange-rates`.

## [0.5.2]

### Changed

- Pin `brightcreations/exchange-rates` to `^0.8.0` (requires bounding-rate repository methods for interpolation).
- Add explicit `brick/math` dependency (`^0.10.2`) for historical conversion math.

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
