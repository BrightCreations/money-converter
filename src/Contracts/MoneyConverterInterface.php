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
     * @param bool $extrapolate Whether to extrapolate the conversion if the exchange rate is not found.
     * @return static
     */
    public function extrapolate(bool $extrapolate = true): static;

    /**
     * Fetch the exchange rates on each conversion.
     *
     * @param bool $needs_fresh Whether to fetch the exchange rates on each conversion.
     * @return static
     */
    public function needsFresh(bool $needs_fresh = true): static;

    /**
     * Throw an exception if the conversion fails.
     *
     * @return static
     */
    public function throwOnFail(): static;

    /**
     * Fetch the exchange rates if the conversion fails.
     *
     * @return static
     */
    public function fetchOnFail(): static;

    /**
     * Check if the conversion fails and the exchange rates are fetched.
     *
     * @return bool
     */
    public function isFetchOnFail(): bool;

    /**
     * Check if the conversion fails and an exception is thrown.
     *
     * @return bool
     */
    public function isThrowOnFail(): bool;

    /**
     * Converts a given amount of money from one currency to another.
     *
     * @param int    $money            Amount of money in minor units (cents).
     * @param string $current_currency Source currency code.
     * @param string $target_currency  Target currency code.
     * @param int|null   $on_fail          Action to take if conversion fails; defaults to throwing an exception.
     *                                   Use ON_FAIL_THROW_EXCEPTION to throw an exception or
     *                                  ON_FAIL_FETCH_EXCHANGE_RATES to fetch exchange rates.
     * @param CarbonInterface|null $date_time Date and time for the conversion; defaults to now.
     *
     * @return int Converted amount of money in minor units (cents).
     *
     * @throws MoneyConversionException conversion fails and on_fail is set to throw.
     */
    public function convert(int $money, string $current_currency, string $target_currency, ?CarbonInterface $date_time = null, ?int $on_fail = null): int;

    /**
     * Converts a given amount of money from one currency to another using the current exchange rate.
     *
     * @param int    $money            Amount of money in minor units (cents).
     * @param string $current_currency Source currency code.
     * @param string $target_currency  Target currency code.
     *
     * @return int Converted amount of money in minor units (cents).
     */
    public function convertCurrent(int $money, string $current_currency, string $target_currency): int;
    
    /**
     * Converts a given amount of money from one currency to another using the fresh exchange rate.
     *
     * @param int    $money            Amount of money in minor units (cents).
     * @param string $current_currency Source currency code.
     * @param string $target_currency  Target currency code.
     *
     * @return int Converted amount of money in minor units (cents).
     */
    public function convertFresh(int $money, string $current_currency, string $target_currency): int;

    /**
     * Converts a given amount of money from one currency to another using the historical exchange rate.
     *
     * @param int    $money            Amount of money in minor units (cents).
     * @param string $current_currency Source currency code.
     * @param string $target_currency  Target currency code.
     * @param CarbonInterface $date_time Date and time for the conversion; defaults to now.
     *
     * @return int Converted amount of money in minor units (cents).
     */
    public function convertHistorical(int $money, string $current_currency, string $target_currency, CarbonInterface $date_time): int;

    /**
     * Converts a given amount of money from one currency to another using the today's exchange rate.
     *
     * @param int    $money            Amount of money in minor units (cents).
     * @param string $current_currency Source currency code.
     * @param string $target_currency  Target currency code.
     *
     * @return int Converted amount of money in minor units (cents).
     */
    public function convertToday(int $money, string $current_currency, string $target_currency): int;
}
