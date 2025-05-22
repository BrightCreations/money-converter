<?php

namespace BrightCreations\MoneyConverter\Concretes;

use Brick\Math\RoundingMode;
use Brick\Money\CurrencyConverter;
use Brick\Money\Exception\CurrencyConversionException;
use Brick\Money\Money;
use BrightCreations\MoneyConverter\Contracts\ExchangeRateServiceInterface;
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use BrightCreations\MoneyConverter\Exceptions\MoneyConversionException;
use Illuminate\Support\Facades\Log;

class MoneyConverter implements MoneyConverterInterface
{

    public function __construct(
        private readonly ExchangeRateServiceInterface $exchangeRateService,
        private readonly CurrencyConverter $converter
    ) { }

    public function convert($money, $current_currency, $target_currency, $date_time = null, $on_fail = null): int {
        $on_fail ??= static::ON_FAIL_THROW_EXCEPTION;
        $money = Money::ofMinor($money, $current_currency);
        try {
            if ($date_time) {
                try {
                    return $this->getConvertedMoneyUsingHistoricalExchangeRates($money, $target_currency, $date_time);
                } catch (CurrencyConversionException $e) {
                    throw new MoneyConversionException("One of these currencies [$current_currency, $target_currency] is not supported, try again later");
                }
            }
            try {
                return $this->getConvertedMoney($money, $target_currency);
            } catch (CurrencyConversionException $e) {
                return match ($on_fail) {
                    static::ON_FAIL_THROW_EXCEPTION         => throw new MoneyConversionException("One of these currencies [$current_currency, $target_currency] is not supported, try again later"),
                    static::ON_FAIL_FETCH_EXCHANGE_RATES    => $this->getConvertedMoneyUsingFreshExchangeRates($money, $target_currency),
                };
            }
        } catch (\Throwable $e) {
            // exception
            Log::error('Exception message: ' . $e->getMessage());
            Log::error('Exception class: ' . get_class($e));
            throw new MoneyConversionException("Error while converting currency - " . $e->getMessage());
        }
    }

    private function getConvertedMoney($money, $target_currency, $rounding_mode = RoundingMode::DOWN): int
    {
        return $this->converter->convert(
            moneyContainer: $money,
            currency: $target_currency,
            roundingMode: $rounding_mode
        )->getMinorAmount()->toInt();
    }

    private function getConvertedMoneyUsingFreshExchangeRates(Money $money, $target_currency, $rounding_mode = RoundingMode::DOWN): int
    {
        $current_currency = $money->getCurrency()->getCurrencyCode();
        // Fetch base currency exchange rates
        $this->exchangeRateService->storeExchangeRates($current_currency);
        // Fetch target currency exchange rates
        $this->exchangeRateService->storeExchangeRates($target_currency);
        // Convert
        return $this->getConvertedMoney($money, $target_currency, $rounding_mode);
    }

    private function getConvertedMoneyUsingHistoricalExchangeRates(Money $money, $target_currency, $date_time, $rounding_mode = RoundingMode::DOWN): int
    {
        if (! $this->exchangeRateService->isSupportHistoricalExchangeRate()) {
            Log::error('Historical exchange rate is not supported');
            throw new MoneyConversionException("Historical exchange rate is not supported");
        }
        $current_currency = $money->getCurrency()->getCurrencyCode();
        // Fetch base currency exchange rates
        $historicalExchangeRate = $this->exchangeRateService->getHistoricalExchangeRate($current_currency, $target_currency, $date_time);

        // Convert
        return $money->multipliedBy($historicalExchangeRate->exchange_rate, $rounding_mode)
            ->getMinorAmount()
            ->toInt();
    }
}
