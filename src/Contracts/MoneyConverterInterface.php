<?php

namespace BrightCreations\MoneyConverter\Contracts;

use BrightCreations\MoneyConverter\Exceptions\MoneyConversionException;
use Carbon\CarbonInterface;

interface MoneyConverterInterface
{
    public const ON_FAIL_THROW_EXCEPTION = 1;

    public const ON_FAIL_FETCH_EXCHANGE_RATES = 2;

    /**
     * Extrapolate the conversion if the exchange rate is not found.
     *
     * When enabled, uses nearest-neighbour extrapolation for historical conversion when the target
     * date is outside the stored rate range: the last known rate on or before the target (when the
     * target is after all data), or the first known rate on or after the target (when before all data).
     * Does not apply when both bounds exist (use interpolate() for dates between two known rates).
     *
     * @param  bool  $extrapolate  Whether to extrapolate the conversion if the exchange rate is not found.
     */
    public function extrapolate(bool $extrapolate = true): static;

    /**
     * Interpolate the conversion if the exchange rate is not found.
     *
     * @param  bool  $interpolate  Whether to interpolate the conversion if the exchange rate is not found.
     */
    public function interpolate(bool $interpolate = true): static;

    /**
     * Fetch the exchange rates on each conversion.
     *
     * @param  bool  $needs_fresh  Whether to fetch the exchange rates on each conversion.
     */
    public function needsFresh(bool $needs_fresh = true): static;

    /**
     * Throw an exception if the conversion fails.
     */
    public function throwOnFail(): static;

    /**
     * Fetch the exchange rates if the conversion fails.
     *
     * When enabled, historical conversion attempts an API fetch on cache miss. Auto-fetch behaviour
     * depends on the configured exchange-rates provider: OpenExchange and ExchangeRateAPI fetch daily
     * rates on miss; World Bank is DB-only (yearly data). Pre-populate history with
     * `php artisan exchange-rates:backfill` when using World Bank or DB-only workflows.
     */
    public function fetchOnFail(): static;

    /**
     * Check if the conversion fails and the exchange rates are fetched.
     */
    public function isFetchOnFail(): bool;

    /**
     * Check if the conversion fails and an exception is thrown.
     */
    public function isThrowOnFail(): bool;

    /**
     * Converts a given amount of money from one currency to another.
     *
     * @param  int  $money  Amount of money in minor units (cents).
     * @param  string  $current_currency  Source currency code.
     * @param  string  $target_currency  Target currency code.
     * @param  CarbonInterface|null  $date_time  Date and time for the conversion; defaults to now.
     * @param  int|null  $on_fail  Action to take if conversion fails; defaults to throwing an exception.
     *                             Use ON_FAIL_THROW_EXCEPTION to throw an exception or
     *                             ON_FAIL_FETCH_EXCHANGE_RATES to fetch exchange rates.
     * @return int Converted amount of money in minor units (cents).
     *
     * @throws MoneyConversionException conversion fails and on_fail is set to throw.
     */
    public function convert(int $money, string $current_currency, string $target_currency, ?CarbonInterface $date_time = null, ?int $on_fail = null): int;

    /**
     * Converts a given amount of money from one currency to another using the current exchange rate.
     *
     * @param  int  $money  Amount of money in minor units (cents).
     * @param  string  $current_currency  Source currency code.
     * @param  string  $target_currency  Target currency code.
     * @return int Converted amount of money in minor units (cents).
     */
    public function convertCurrent(int $money, string $current_currency, string $target_currency): int;

    /**
     * Converts a given amount of money from one currency to another using the fresh exchange rate.
     *
     * @param  int  $money  Amount of money in minor units (cents).
     * @param  string  $current_currency  Source currency code.
     * @param  string  $target_currency  Target currency code.
     * @return int Converted amount of money in minor units (cents).
     */
    public function convertFresh(int $money, string $current_currency, string $target_currency): int;

    /**
     * Converts a given amount of money from one currency to another using the historical exchange rate.
     *
     * Exact database lookup matches by date only (ignores time component). Prefer passing
     * `startOfDay()` for predictable results. Bounding, interpolation, and extrapolation use full datetime.
     *
     * @param  int  $money  Amount of money in minor units (cents).
     * @param  string  $current_currency  Source currency code.
     * @param  string  $target_currency  Target currency code.
     * @param  CarbonInterface  $date_time  Date and time for the conversion.
     * @return int Converted amount of money in minor units (cents).
     */
    public function convertHistorical(int $money, string $current_currency, string $target_currency, CarbonInterface $date_time): int;

    /**
     * Converts a given amount of money from one currency to another using the today's exchange rate.
     *
     * @param  int  $money  Amount of money in minor units (cents).
     * @param  string  $current_currency  Source currency code.
     * @param  string  $target_currency  Target currency code.
     * @return int Converted amount of money in minor units (cents).
     */
    public function convertToday(int $money, string $current_currency, string $target_currency): int;
}
