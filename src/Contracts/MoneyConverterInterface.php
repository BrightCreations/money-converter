<?php

namespace BrightCreations\MoneyConverter\Contracts;

use BrightCreations\MoneyConverter\Exceptions\MoneyConversionException;
use Carbon\CarbonInterface;

interface MoneyConverterInterface
{
    public const ON_FAIL_THROW_EXCEPTION = 1;
    public const ON_FAIL_FETCH_EXCHANGE_RATES = 2;

    /**
     * Converts a given amount of money from one currency to another.
     *
     * @param int    $money            Amount of money in minor units (cents).
     * @param string $current_currency Source currency code.
     * @param string $target_currency  Target currency code.
     * @param bool   $on_fail          Action to take if conversion fails; defaults to throwing an exception.
     *                                   Use ON_FAIL_THROW_EXCEPTION to throw an exception or
     *                                  ON_FAIL_FETCH_EXCHANGE_RATES to fetch exchange rates.
     * @param CarbonInterface|null $date_time Date and time for the conversion; defaults to now.
     *
     * @return int Converted amount of money in minor units (cents).
     *
     * @throws MoneyConversionException conversion fails and on_fail is set to throw.
     */
    public function convert($money, $current_currency, $target_currency, $date_time = null, $on_fail = null): int;
}
