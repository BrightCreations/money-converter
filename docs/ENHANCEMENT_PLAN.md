# Money Converter + Exchange Rates — Joint Enhancement Plan

**Status:** Draft  
**Last updated:** 2026-06-30  
**Primary package:** `brightcreations/money-converter` (v0.5.1)  
**Supporting package:** `brightcreations/exchange-rates` (v0.8.1)

---

## Purpose

This document is the **single roadmap** for evolving both packages together. End users integrate **money-converter** — they call `convert()`, `convertHistorical()`, and the fluent API. **exchange-rates** is the rate-ingestion and storage layer that money-converter depends on; its enhancements should unlock or stabilize conversion behaviour, not compete for attention in user-facing docs.

### Design principles

1. **User-first API** — Every public surface on money-converter should be predictable, documented, and safe in a shared Laravel container.
2. **exchange-rates stays infrastructure** — Fetch, store, query rates. Interpolation/extrapolation logic lives in money-converter unless it is pure data access.
3. **Version together** — money-converter pins a minimum exchange-rates version; breaking repository or facade changes require coordinated releases.
4. **Document the happy path** — One integration guide: install exchange-rates → configure APIs → migrate → convert with money-converter.

---

## Current integration snapshot

```
User application
       │
       ▼
MoneyConverter (singleton) ──► convert / convertHistorical / fluent flags
       │
       ├── Current/Fresh path ──► Brick CurrencyConverter ◄── PDOProvider (reads DB)
       │                              └── on fail ──► ExchangeRate::storeExchangeRates()
       │
       └── Historical path ──► manual rate lookup + Money::multipliedBy()
                1. ExchangeRateRepository::getHistoricalExchangeRate()     [DB, date-only]
                2. HistoricalExchangeRate::getHistoricalExchangeRate()    [API auto-fetch*]
                3. Proxy cross-rate via USD (configurable)
                4. Interpolation via getBoundingHistoricalRates()           [needs ER ≥ 0.5.5]
                5. Extrapolation                                              [NOT IMPLEMENTED]
```

\* API auto-fetch on step 2 works for OpenExchange / ExchangeRateAPI providers only. World Bank is DB-only and yearly.

### Known gaps (summary)

| Area | money-converter | exchange-rates |
|------|-----------------|----------------|
| Version coupling | `*` (unpinned) | — |
| Extrapolation | Flag exists, no implementation | Bounding helpers exist, undocumented |
| Docs | Stale README, no setup guide | Bounding methods missing from docs/facade |
| Tests | None | Pest suite (no bounding/interpolation tests) |
| Architecture | Singleton + mutable fluent state | — |
| Dead code | `ExchangeRateServiceInterface` unused | — |

---

## Target outcomes (what users should get)

After this plan is executed, a developer should be able to:

1. `composer require brightcreations/money-converter` and follow **one README** to get conversions working.
2. Convert current, fresh, today, and historical amounts with clear behaviour for `throwOnFail` vs `fetchOnFail`.
3. Opt into `interpolate()` and `extrapolate()` with documented limits (provider, date range, proxy currency).
4. Trust version compatibility via an explicit composer constraint, not trial and error.
5. Run a test suite that covers the historical fallback chain against exchange-rates fixtures.

---

## Release & versioning strategy

| Rule | Detail |
|------|--------|
| **Pin dependency** | money-converter `composer.json`: `"brightcreations/exchange-rates": "^0.8.0"` (minimum `^0.6.0` if broader support required) |
| **Coordinated bumps** | When exchange-rates adds repository methods money-converter needs, release exchange-rates first, then bump money-converter constraint |
| **Changelog cross-links** | money-converter CHANGELOG references exchange-rates version requirements per release |
| **Tag cadence** | Patch: bug/docs. Minor: new conversion strategies or config. Major: breaking interface or default behaviour |

---

## Phase 1 — Foundation & trust (P0)

**Goal:** Safe installs, no misleading API, correct error messages.  
**User-visible win:** Reliable conversions and honest failure modes.

### money-converter

| # | Task | Files / notes |
|---|------|---------------|
| 1.1 | Pin `brightcreations/exchange-rates` to `^0.8.0` | `composer.json` |
| 1.2 | Add explicit `brick/math` dependency (used by `BigDecimal` in historical math) | `composer.json` |
| 1.3 | Remove dead `ExchangeRateServiceInterface` | Delete `src/Contracts/ExchangeRateServiceInterface.php` |
| 1.4 | Fix misleading error message in `convertCurrent()` non-fresh catch block | `src/Concretes/MoneyConverter.php` ~L123 |
| 1.5 | Add `CHANGELOG.md` with v0.5.x history and exchange-rates requirement | new file |

### exchange-rates (supporting)

| # | Task | Files / notes |
|---|------|---------------|
| 1.6 | Add `@method` entries for `getBoundingHistoricalRates`, `getPreviousHistoricalRate`, `getNextHistoricalRate` | `src/Facades/ExchangeRateRepository.php` |
| 1.7 | Document bounding-rate methods in `docs/repository.md` with interpolation use-case note pointing to money-converter | `docs/repository.md` |
| 1.8 | Add Pest tests for `getBoundingHistoricalRates` edge cases (0/1/2 bounds, same record) | `tests/Unit/` |

**Exit criteria:** Composer install resolves compatible versions; facade IDE hints complete; bounding repository behaviour tested.

---

## Phase 2 — Complete historical strategies (P0)

**Goal:** `extrapolate()` does real work; users understand provider limits.  
**User-visible win:** Historical conversion succeeds for more dates without silent no-ops.

### money-converter

| # | Task | Detail |
|---|------|--------|
| 2.1 | **Implement extrapolation** (step 5 in historical chain) | Use `getPreviousHistoricalRate` or `getNextHistoricalRate` from exchange-rates when target date is outside stored range. Strategy options: (a) nearest-neighbour rate, (b) linear extrapolation from last two known points. Document chosen strategy. |
| 2.2 | Guard extrapolation when only one bound exists | Return null / fall through to final exception with clear message |
| 2.3 | Document `fetchOnFail` provider matrix | README + inline docblock: OpenExchange/ExchangeRateAPI auto-fetch; World Bank DB-only; recommend `exchange-rates:backfill` |
| 2.4 | Align datetime semantics | Document that exact historical lookup is **date-only** (`whereDate`); recommend `startOfDay()` for `convertHistorical` / `convertToday` |
| 2.5 | Use bulk fresh fetch | Replace two `storeExchangeRates()` calls with `storeBulkExchangeRatesForMultipleCurrencies([$from, $to])` in `getConvertedMoneyUsingFreshExchangeRates()` |

### exchange-rates (supporting)

| # | Task | Detail |
|---|------|--------|
| 2.6 | Document provider historical capabilities in README | Table: auto-fetch vs DB-only, granularity (daily vs yearly) |
| 2.7 | Optional: `getNearestHistoricalRate()` helper | Only if extrapolation needs a single canonical accessor; otherwise money-converter uses existing previous/next methods |

**Exit criteria:** `extrapolate()` changes conversion outcome in tests; README explains when historical API fetch will not run.

---

## Phase 3 — Developer experience & documentation (P1)

**Goal:** money-converter README is the integration bible.  
**User-visible win:** Onboarding in minutes, not archaeology across two repos.

### money-converter (primary docs)

| # | Task | Detail |
|---|------|--------|
| 3.1 | **Rewrite README** | Correct package name, version, minor-units convention (`10000` = 100.00) |
| 3.2 | **Integration guide section** | Step-by-step: require both packages, publish configs, run migrations, set API env vars, seed/backfill rates, first `convert()` call |
| 3.3 | **API reference** | All methods: `convert`, `convertCurrent`, `convertFresh`, `convertToday`, `convertHistorical`, fluent flags, `ON_FAIL_*` constants |
| 3.4 | **Configuration reference** | `rounding_mode`, `proxy_currency_code`, `default_provider`, PDO column mapping vs exchange-rates schema |
| 3.5 | **Facade usage** | Document `MoneyConverter` facade alongside interface injection |
| 3.6 | **Exceptions** | When `MoneyConversionException` is thrown; difference from `InvalidArgumentException` on bad `$on_fail` |
| 3.7 | Link to exchange-rates docs for rate sourcing only | “How rates get into the database” → exchange-rates README |

### exchange-rates (supporting docs)

| # | Task | Detail |
|---|------|--------|
| 3.8 | Add “Consuming with money-converter” section to README | Short: install money-converter, it reads same tables, link back |
| 3.9 | Sync `docs/repository.md` with current interface | `provider` param, bounding methods, `getExchangeRatesByTargetCurrency` |
| 3.10 | Mention money-converter in `docs/examples.md` | One historical + interpolation example |

**Exit criteria:** New user can complete setup using only money-converter README; exchange-rates README has a short consumer section.

---

## Phase 4 — Architecture & quality (P1–P2)

**Goal:** Safe concurrent use, consistent math, automated regression coverage.  
**User-visible win:** Fluent calls don’t leak across requests; amounts match expectations.

### money-converter

| # | Task | Detail |
|---|------|--------|
| 4.1 | **Fix singleton mutable state** | Options (pick one): (a) immutable options DTO passed to `convert()`, (b) `clone` before applying fluent flags, (c) bind as transient/scoped instead of singleton. **Recommended:** options object + deprecate fluent setters on shared instance. |
| 4.2 | **Rounding alignment** | Document or align proxy/interpolation math with exchange-rates `HALF_UP` @ 10dp for cross-rates; keep `rounding_mode` config for final minor-unit conversion |
| 4.3 | **Test suite (Pest + Testbench)** | See test matrix below |
| 4.4 | CI workflow | GitHub Actions: pint, pest, min PHP 8.1 |

#### money-converter test matrix

| Test | Covers |
|------|--------|
| Same-currency short-circuit | All convert methods |
| `convertCurrent` with seeded PDO rates | PDO + exchange-rates migrations |
| `convertCurrent` + `fetchOnFail` | Mock `ExchangeRate::storeExchangeRates` |
| `convertFresh` | Bulk store called |
| Historical exact date hit | Repository seeded |
| Historical proxy via USD | Missing direct pair |
| `interpolate()` between two bounds | `getBoundingHistoricalRates` |
| `extrapolate()` beyond range | Previous/next rates |
| `throwOnFail` vs `fetchOnFail` on historical | Provider behaviour |
| Invalid `$on_fail` | `InvalidArgumentException` |

### exchange-rates (supporting)

| # | Task | Detail |
|---|------|--------|
| 4.5 | Add integration test fixture helpers | Shared seeders or factories consumable by money-converter tests (optional separate `testbench` trait or documented seeder pattern) |
| 4.6 | Document date-only vs datetime in repository methods | Clarify `whereDate` on exact get vs full datetime on bounding |

**Exit criteria:** CI green on money-converter; fluent state documented or fixed; cross-package historical tests pass.

---

## Phase 5 — Advanced features (P2–P3)

**Goal:** Power-user ergonomics without complicating the default path.

### money-converter

| # | Task | Detail |
|---|------|--------|
| 5.1 | Config-driven `ConfigurableProvider` | Move hardcoded test rates to `config/money-converter.php` under `exchange_rates.configurable` for local dev |
| 5.2 | `convertBulk()` | Accept array of `{amount, from, to, date?}` using exchange-rates bulk repository methods where applicable |
| 5.3 | Conversion result DTO | Optional return type with rate used, strategy applied, provider — for audit trails |
| 5.4 | Laravel 9 support cleanup | Align `illuminate/support` constraint with other illuminate packages (^10+) |

### exchange-rates (supporting)

| # | Task | Detail |
|---|------|--------|
| 5.5 | Repository bulk helpers for bounding | Optional `getBulkBoundingHistoricalRates` if `convertBulk` needs it |
| 5.6 | Provider capability interface | e.g. `supportsHistoricalApiFetch(): bool` so money-converter can branch without hardcoding provider class names |

**Exit criteria:** Advanced features opt-in; default `convert()` path unchanged.

---

## Package responsibility matrix

| Concern | money-converter | exchange-rates |
|---------|-----------------|----------------|
| Currency conversion math (minor units) | ✅ | — |
| Brick Money integration | ✅ | — |
| Fetch rates from external APIs | delegates | ✅ |
| Store rates in DB | delegates | ✅ |
| Repository / historical queries | consumes | ✅ |
| Interpolation / extrapolation algorithms | ✅ | supplies bounding data only |
| User-facing documentation | ✅ primary | ✅ rate sourcing & repository |
| HTTP API for rates | — | ✅ (optional routes) |
| Backfill commands | — | ✅ `exchange-rates:backfill` |

---

## Suggested timeline

| Phase | Focus | Suggested effort |
|-------|--------|------------------|
| **Phase 1** | Foundation & trust | 1–2 days |
| **Phase 2** | Historical strategies complete | 2–3 days |
| **Phase 3** | Documentation | 2–3 days |
| **Phase 4** | Architecture & tests | 3–5 days |
| **Phase 5** | Advanced (as needed) | backlog |

Phases 1–3 can ship as **money-converter v0.6.0** with **exchange-rates v0.8.2** (doc/test patch). Phase 4 targets **v0.7.0**. Phase 5 is **v0.8.0+**.

---

## exchange-rates repo mirror

The following items from this plan should be tracked in **exchange-rates** (copy or link `docs/ENHANCEMENT_PLAN.md` section “exchange-rates tasks”):

- [x] 1.6 — Facade `@method` for bounding rates
- [x] 1.7 — `docs/repository.md` bounding section
- [x] 1.8 — Bounding repository tests
- [ ] 2.6 — Provider historical capability table
- [ ] 2.7 — Optional nearest-rate helper (if needed)
- [ ] 3.8 — “Consuming with money-converter” README section
- [ ] 3.9 — Sync repository docs
- [ ] 3.10 — money-converter example in docs
- [ ] 4.5 — Test fixture helpers
- [ ] 4.6 — Date-only vs datetime documentation
- [ ] 5.5 — Bulk bounding helper (if needed)
- [ ] 5.6 — Provider capability interface (if needed)

---

## Decision log

Record choices here as work proceeds.

| Date | Decision | Rationale |
|------|----------|-----------|
| 2026-06-30 | Phase 1 complete | exchange-rates v0.8.2 (facade, docs, tests); money-converter 0.5.2 (pin ER ^0.8.0, brick/math, dead code removal, error fix, CHANGELOG) |
| 2026-06-30 | money-converter owns interpolation/extrapolation | exchange-rates is data layer; keeps user API in one package |
| 2026-06-30 | Pin exchange-rates `^0.8.0` | `getBoundingHistoricalRates` required for interpolation |
| | Extrapolation strategy TBD | nearest-neighbour vs linear — pick during Phase 2 implementation |
| | Singleton fix approach TBD | options DTO preferred; evaluate BC impact during Phase 4 |

---

## References

- money-converter integration review (2026-06-30): historical fallback chain, gap analysis
- exchange-rates repository: `getBoundingHistoricalRates`, `getPreviousHistoricalRate`, `getNextHistoricalRate` (since v0.5.5)
- exchange-rates provider docs: OpenExchange / ExchangeRateAPI (daily + auto-fetch) vs World Bank (yearly, DB-only reads)
