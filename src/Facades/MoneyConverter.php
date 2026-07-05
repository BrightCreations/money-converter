<?php

namespace BrightCreations\MoneyConverter\Facades;

use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use BrightCreations\MoneyConverter\DTOs\MoneyConversionResult;
use BrightCreations\MoneyConverter\DTOs\MoneyConverterOptions;
use Illuminate\Support\Facades\Facade;

/**
 * @method static MoneyConverterInterface extrapolate(bool $extrapolate = true)
 * @method static MoneyConverterInterface interpolate(bool $interpolate = true)
 * @method static MoneyConverterInterface needsFresh(bool $needs_fresh = true)
 * @method static MoneyConverterInterface throwOnFail()
 * @method static MoneyConverterInterface fetchOnFail()
 * @method static MoneyConverterInterface withOptions(MoneyConverterOptions $options)
 * @method static bool isFetchOnFail()
 * @method static bool isThrowOnFail()
 * @method static int convert(int $money, string $current_currency, string $target_currency, ?CarbonInterface $date_time = null, ?int $on_fail = null, ?MoneyConverterOptions $options = null)
 * @method static MoneyConversionResult convertWithResult(int $money, string $current_currency, string $target_currency, ?CarbonInterface $date_time = null, ?int $on_fail = null, ?MoneyConverterOptions $options = null)
 * @method static int[] convertBulk(array $items, ?MoneyConverterOptions $options = null)
 * @method static MoneyConversionResult[] convertBulkWithResult(array $items, ?MoneyConverterOptions $options = null)
 * @method static int convertCurrent(int $money, string $current_currency, string $target_currency, ?MoneyConverterOptions $options = null)
 * @method static int convertFresh(int $money, string $current_currency, string $target_currency, ?MoneyConverterOptions $options = null)
 * @method static int convertHistorical(int $money, string $current_currency, string $target_currency, CarbonInterface $date_time, ?MoneyConverterOptions $options = null)
 * @method static int convertToday(int $money, string $current_currency, string $target_currency, ?MoneyConverterOptions $options = null)
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
