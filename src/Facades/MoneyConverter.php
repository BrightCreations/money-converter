<?php

namespace BrightCreations\MoneyConverter\Facades;

use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static MoneyConverterInterface extrapolate(bool $extrapolate = true)
 * @method static MoneyConverterInterface needsFresh(bool $needs_fresh = true)
 * @method static MoneyConverterInterface throwOnFail()
 * @method static MoneyConverterInterface fetchOnFail()
 * @method static bool isFetchOnFail()
 * @method static bool isThrowOnFail()
 * @method static int convert(int $money, string $current_currency, string $target_currency, ?CarbonInterface $date_time = null, ?int $on_fail = null)
 * @method static int convertCurrent(int $money, string $current_currency, string $target_currency)
 * @method static int convertFresh(int $money, string $current_currency, string $target_currency)
 * @method static int convertHistorical(int $money, string $current_currency, string $target_currency, CarbonInterface $date_time)
 * @method static int convertToday(int $money, string $current_currency, string $target_currency)
 */
class MoneyConverter extends Facade
{
    public const ON_FAIL_THROW_EXCEPTION = 1;
    public const ON_FAIL_FETCH_EXCHANGE_RATES = 2;

    protected static function getFacadeAccessor(): string
    {
        return MoneyConverterInterface::class;
    }
}
