# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`brightcreations/money-converter` is a standalone Laravel package (not an app) that converts monetary amounts between currencies, expressed in **minor units** (cents), using exchange rates stored in a database by the sibling `brightcreations/exchange-rates` package. It wraps [Brick Money](https://github.com/brick/money) for the actual currency arithmetic and rounding.

- Namespace: `BrightCreations\MoneyConverter\` → `src/`
- Public entry point: `BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface`, implemented by `src/Concretes/MoneyConverter.php`. Resolve it via the container, constructor injection, or the `MoneyConverter` facade (`src/Facades/MoneyConverter.php`).
- Requires PHP `>=8.1`, Laravel (`illuminate/*`) `^10 || ^11 || ^12`. It is Laravel-specific, not framework-agnostic (uses the service container, config, facades, Eloquent, events).
- Published version: `v0.12.2` (see `composer.json`).

## Commands

```bash
composer install                 # install dependencies (Laravel + brick/money + brightcreations/exchange-rates)
composer test                    # runs ./vendor/bin/pest (Pest 3, with orchestra/testbench for the Laravel harness)
composer pint                    # ./vendor/bin/pint — Laravel Pint code style fixer
vendor/bin/pint --test           # check style without fixing (what CI runs)
```

CI (`.github/workflows/`) runs on PHP 8.2/8.3: `composer install`, `pint --test`, then `pest`. Tests live in `tests/Unit/`, using `tests/TestCase.php` (testbench) and `tests/Concerns/SeedsExchangeRates.php` for fixtures.

## Architecture

- **`MoneyConverterServiceProvider`** (`src/MoneyConverterServiceProvider.php`) — auto-discovered by Laravel via `composer.json`'s `extra.laravel.providers`. Registers:
  - `ExchangeRatePDOProviderBuilderInterface` / `ExchangeRateConfigurableProviderBuilderInterface` (`src/Concretes/Builders/`) — build a Brick `ExchangeRateProvider` either from the DB table populated by `exchange-rates`, or from static rates in config (for local dev without a DB).
  - `Brick\Money\ExchangeRateProvider` and `Brick\Money\CurrencyConverter` as singletons, built from whichever provider builder `config('money-converter.default_provider')` selects (`ExchangeRateProvidersEnum`).
  - `MoneyConverterInterface` → `Concretes\MoneyConverter` (singleton).
  - Also registers `MoneyConverterEventServiceProvider` for the `ConversionPerformed` event/listener wiring.
- **Relationship to `../exchange-rates`**: this package is a hard dependency (`brightcreations/exchange-rates: ^0.9.0`). It does *not* fetch rates itself day-to-day — `exchange-rates` populates `currency_exchange_rates` / `currency_exchange_rates_history`; `MoneyConverter` reads them (directly via PDO provider, or via `exchange-rates`' facades `ExchangeRate`, `ExchangeRateRepository`, `HistoricalExchangeRate` for fresh/historical lookups — see the `use` block at the top of `src/Concretes/MoneyConverter.php`).
- **Relationship to `brick/money`**: `MoneyConverter::convert*()` methods build `Brick\Money\Money` objects and delegate rate lookup/currency conversion arithmetic to `Brick\Money\CurrencyConverter`; `brick/math`'s `RoundingMode` and `BigDecimal` handle precision.
- **DTOs** (`src/DTOs/`): `ConversionItem` (bulk conversion input row), `MoneyConversionResult` (amount + rate + strategy + provider), `MoneyConverterOptions` (immutable per-call/config options, built via `MoneyConverterOptions::defaults()->withFetchOnFail()->withInterpolate()` etc.).
- **Consuming apps** (e.g. `../dazu-core`) require both `brightcreations/money-converter` and `brightcreations/exchange-rates` in `composer.json`; both service providers auto-register. Typical usage: inject `MoneyConverterInterface`, or use the `MoneyConverter` facade. See `README.md` "Integration guide" for the full zero-to-conversion setup (publish config/migrations, configure provider API keys, backfill/populate rates, then call `convert()`).

## Conventions worth knowing

- All amounts are integers in **minor units** (e.g. `10000` = 100.00 USD); same-currency conversion returns the input unchanged.
- Rounding uses `config('money-converter.rounding_mode')`, default `RoundingMode::DOWN`, applied when converting back to minor units.
- `MoneyConverter` is a container **singleton**, but fluent methods (`fetchOnFail()`, `extrapolate()`, `interpolate()`, `needsFresh()`, `throwOnFail()`, `withOptions()`) each return a **new instance** — they never mutate the shared singleton or leak state between calls.
- Historical conversion (`convertHistorical()`) runs a fallback chain: exact DB lookup (date-only, `whereDate`) → optional API fetch (`fetchOnFail()`) → proxy-currency cross-rate (`config('money-converter.proxy_currency_code')`, default `USD`) → optional `interpolate()` (linear, between two bounding rates) → optional `extrapolate()` (nearest single stored rate). Exact-date lookups match by date only; interpolation/extrapolation bounds use full datetime.
- Failure modes are explicit constants on `MoneyConverterInterface`: `ON_FAIL_THROW_EXCEPTION` (default) vs `ON_FAIL_FETCH_EXCHANGE_RATES`. Unsupported/unresolvable pairs raise `MoneyConversionException` (wraps the underlying cause); an invalid `$on_fail` value raises `InvalidArgumentException`.
- Every successful conversion (one event per bulk item) dispatches `Events\ConversionPerformed`; the default listener `Listeners\LogConversionPerformed` logs input/output amounts, currencies, rate, strategy, provider. Configurable/disableable via `config('money-converter.events')` and `MONEY_CONVERTER_DISPATCH_CONVERSION_EVENTS`.
- Provider capabilities differ for historical auto-fetch: Open Exchange Rates and Exchange Rate API support daily auto-fetch on a miss; World Bank is DB-only (yearly granularity) — use `php artisan exchange-rates:backfill` for those.

## Ecosystem

This package is part of the DAZU fintech ecosystem, developed alongside 7 sibling repos. It is consumed as a Composer dependency (`brightcreations/money-converter`) by DAZU apps, notably `../dazu-core`. Each sibling repo has its own `CLAUDE.md`:

- `../dazu-core` — main DAZU app; requires this package (and `../exchange-rates`) via Composer
- `../dazu-id-api` — identity API
- `../shikaty-core` — sibling core app
- `../dazu-wealth-api` — wealth API
- `../exchange-rates` — sibling package this one depends on (`brightcreations/exchange-rates`); populates the `currency_exchange_rates`(`_history`) tables this package reads
- `../dazu-orchestrator` — orchestration service
- `../dazu-infra` — infrastructure/IaC
- `../cicd-scripts` — shared CI/CD scripts
