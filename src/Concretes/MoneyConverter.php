<?php

namespace BrightCreations\MoneyConverter\Concretes;

use Brick\Math\RoundingMode;
use Brick\Money\CurrencyConverter;
use Brick\Money\Exception\CurrencyConversionException;
use Brick\Money\Money;
use BrightCreations\MoneyConverter\Contracts\ExchangeRateServiceInterface;
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use BrightCreations\MoneyConverter\Exceptions\MoneyConversionException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class MoneyConverter implements MoneyConverterInterface
{
    public const LOG_PREFIX = '[MoneyConverter]';

    public function __construct(
        private readonly ExchangeRateServiceInterface $exchangeRateService,
        private readonly CurrencyConverter $converter
    ) { }

    public function convert(int $money, string $current_currency, string $target_currency, ?CarbonInterface $date_time = null, ?int $on_fail = null): int {
        $on_fail ??= static::ON_FAIL_THROW_EXCEPTION;
        $money = Money::ofMinor($money, $current_currency);
        try {
            if ($date_time) {
                try {
                    return $this->getConvertedMoneyUsingHistoricalExchangeRates($money, $target_currency, $date_time);
                } catch (\Throwable $th) {
                    $this->logError($th, func_get_args());
                    throw new MoneyConversionException(self::LOG_PREFIX . " Error while converting currency using historical exchange rates: " . $th->getMessage(), $th);
                }
            }
            try {
                return $this->getConvertedMoney($money, $target_currency);
            } catch (CurrencyConversionException $e) {
                if ($on_fail === static::ON_FAIL_FETCH_EXCHANGE_RATES) {
                    return $this->getConvertedMoneyUsingFreshExchangeRates($money, $target_currency);
                }

                $this->logError($e, func_get_args());
                throw new MoneyConversionException(self::LOG_PREFIX . " Error while converting currency with current exchange rates: " . $e->getMessage(), $e);
            } catch (\Throwable $th) {
                $this->logError($th, func_get_args());
                throw new MoneyConversionException(self::LOG_PREFIX . " Error while converting currency using fresh exchange rates: " . $th->getMessage(), $th);
            }
        } catch (\Throwable $th) {
            $this->logError($th, func_get_args());
            throw new MoneyConversionException(self::LOG_PREFIX . " Error while converting currency: " . $th->getMessage(), $th);
        }
    }

    private function getConvertedMoney($money, $target_currency, $rounding_mode = RoundingMode::Down): int
    {
        return $this->converter->convert(
            moneyContainer: $money,
            currency: $target_currency,
            roundingMode: $rounding_mode
        )->getMinorAmount()->toInt();
    }

    private function getConvertedMoneyUsingFreshExchangeRates(Money $money, $target_currency, $rounding_mode = RoundingMode::Down): int
    {
        $current_currency = $money->getCurrency()->getCurrencyCode();
        // Fetch base currency exchange rates
        $this->exchangeRateService->storeExchangeRates($current_currency);
        // Fetch target currency exchange rates
        $this->exchangeRateService->storeExchangeRates($target_currency);
        // Convert
        return $this->getConvertedMoney($money, $target_currency, $rounding_mode);
    }

    private function getConvertedMoneyUsingHistoricalExchangeRates(Money $money, $target_currency, $date_time, $rounding_mode = RoundingMode::Down): int
    {
        if (! $this->exchangeRateService->isSupportHistoricalExchangeRate()) {
            Log::error(self::LOG_PREFIX . ' Historical exchange rate is not supported for the exchange rate service');
            Log::error(self::LOG_PREFIX . ' Exchange rate service: ' . get_class($this->exchangeRateService));
            throw new MoneyConversionException(self::LOG_PREFIX . " Historical exchange rate is not supported for the exchange rate service");
        }
        $current_currency = $money->getCurrency()->getCurrencyCode();
        // Fetch base currency exchange rates
        $historicalExchangeRate = $this->exchangeRateService->getHistoricalExchangeRate($current_currency, $target_currency, $date_time);

        // Convert
        return $money->multipliedBy($historicalExchangeRate->exchange_rate, $rounding_mode)
            ->getMinorAmount()
            ->toInt();
    }

    private function logError(\Throwable $th, array $func_args): void
    {
        Log::error(self::LOG_PREFIX . ' Error: ' . $th->getMessage());
        Log::error(self::LOG_PREFIX . ' Method args: ' . json_encode($func_args));
        Log::error(self::LOG_PREFIX . ' Exception class: ' . get_class($th));
        Log::debug(self::LOG_PREFIX . ' Trace: ' . $th->getTraceAsString());
    }
}
