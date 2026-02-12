<?php

namespace BrightCreations\MoneyConverter\Concretes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\CurrencyConverter;
use Brick\Money\Exception\CurrencyConversionException;
use Brick\Money\Money;
use BrightCreations\ExchangeRates\Facades\ExchangeRate;
use BrightCreations\ExchangeRates\Facades\ExchangeRateRepository;
use BrightCreations\ExchangeRates\Facades\HistoricalExchangeRate;
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use BrightCreations\MoneyConverter\Exceptions\MoneyConversionException;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final class MoneyConverter implements MoneyConverterInterface
{
    private const LOG_PREFIX = '[MoneyConverter]';

    private bool $extrapolate;
    private bool $needs_fresh;
    private int $on_fail;
    private RoundingMode $rounding_mode;

    public function __construct(
        private readonly CurrencyConverter $converter
    ) {
        $this->extrapolate = false;
        $this->needs_fresh = false;
        $this->on_fail = static::ON_FAIL_THROW_EXCEPTION;
        $this->rounding_mode = Config::get('money-converter.rounding_mode', RoundingMode::DOWN);
    }

    public function extrapolate(bool $extrapolate = true): static
    {
        $this->extrapolate = $extrapolate;
        return $this;
    }

    public function needsFresh(bool $needs_fresh = true): static
    {
        $this->needs_fresh = $needs_fresh;
        return $this;
    }

    public function throwOnFail(): static
    {
        $this->on_fail = static::ON_FAIL_THROW_EXCEPTION;
        return $this;
    }

    public function fetchOnFail(): static
    {
        $this->on_fail = static::ON_FAIL_FETCH_EXCHANGE_RATES;
        return $this;
    }

    public function isFetchOnFail(): bool
    {
        return $this->on_fail === static::ON_FAIL_FETCH_EXCHANGE_RATES;
    }

    public function isThrowOnFail(): bool
    {
        return $this->on_fail === static::ON_FAIL_THROW_EXCEPTION;
    }

    public function convert(int $money, string $current_currency, string $target_currency, ?CarbonInterface $date_time = null, ?int $on_fail = null): int {
        if ($current_currency === $target_currency) {
            return $money;
        }

        if ($on_fail !== null) {
            match ($on_fail) {
                static::ON_FAIL_FETCH_EXCHANGE_RATES => $this->fetchOnFail(),
                static::ON_FAIL_THROW_EXCEPTION => $this->throwOnFail(),
                default => throw new \InvalidArgumentException(self::LOG_PREFIX . ' Invalid on fail value: ' . $on_fail . '. Expected: ' . static::ON_FAIL_FETCH_EXCHANGE_RATES . ' for fetching exchange rates' . ' or ' . static::ON_FAIL_THROW_EXCEPTION . ' for throwing an exception. Got: ' . $on_fail),
            };
        }

        if ($date_time) {
            return $this->convertHistorical($money, $current_currency, $target_currency, $date_time);
        }

        if ($this->needs_fresh) {
            return $this->convertFresh($money, $current_currency, $target_currency);
        }

        return $this->convertCurrent($money, $current_currency, $target_currency);
    }

    public function convertCurrent(int $money, string $current_currency, string $target_currency): int
    {
        if ($current_currency === $target_currency) {
            return $money;
        }

        $money = Money::ofMinor($money, $current_currency);
        try {
            return $this->getConvertedMoney($money, $target_currency);
        } catch (CurrencyConversionException $e) {
            if ($this->isFetchOnFail()) {
                return $this->getConvertedMoneyUsingFreshExchangeRates($money, $target_currency);
            }

            $this->logError($e, func_get_args(), __METHOD__);
            throw new MoneyConversionException(self::LOG_PREFIX . " Error while converting currency with current exchange rates: " . $e->getMessage(), $e);
        } catch (\Throwable $th) {
            $this->logError($th, func_get_args(), __METHOD__);
            throw new MoneyConversionException(self::LOG_PREFIX . " Error while converting currency using fresh exchange rates: " . $th->getMessage(), $th);
        }
    }

    public function convertFresh(int $money, string $current_currency, string $target_currency): int
    {
        if ($current_currency === $target_currency) {
            return $money;
        }

        $money = Money::ofMinor($money, $current_currency);
        try {
            return $this->getConvertedMoneyUsingFreshExchangeRates($money, $target_currency);
        } catch (\Throwable $th) {
            $this->logError($th, func_get_args(), __METHOD__);
            throw new MoneyConversionException(self::LOG_PREFIX . " Error while converting currency using fresh exchange rates: " . $th->getMessage(), $th);
        }
    }

    public function convertHistorical(int $money, string $current_currency, string $target_currency, CarbonInterface $date_time): int
    {
        if ($current_currency === $target_currency) {
            return $money;
        }

        $money = Money::ofMinor($money, $current_currency);
        try {
            return $this->getConvertedMoneyUsingHistoricalExchangeRates($money, $target_currency, $date_time);
        } catch (\Throwable $th) {
            $this->logError($th, func_get_args(), __METHOD__);
            throw new MoneyConversionException(self::LOG_PREFIX . " Error while converting currency using historical exchange rates: " . $th->getMessage(), $th);
        }
    }

    public function convertToday(int $money, string $current_currency, string $target_currency): int
    {
        if ($current_currency === $target_currency) {
            return $money;
        }

        $money = Money::ofMinor($money, $current_currency);
        try {
            return $this->getConvertedMoneyUsingHistoricalExchangeRates($money, $target_currency, Carbon::now()->startOfDay());
        } catch (\Throwable $th) {
            $this->logError($th, func_get_args(), __METHOD__);
            throw new MoneyConversionException(self::LOG_PREFIX . " Error while converting currency using today's exchange rates: " . $th->getMessage(), $th);
        }
    }

    private function getConvertedMoney($money, $target_currency): int
    {
        return $this->converter->convert(
            moneyContainer: $money,
            currency: $target_currency,
            roundingMode: $this->rounding_mode
        )->getMinorAmount()->toInt();
    }

    private function getConvertedMoneyUsingFreshExchangeRates(Money $money, $target_currency): int
    {
        $current_currency = $money->getCurrency()->getCurrencyCode();
        // Fetch base currency exchange rates
        ExchangeRate::storeExchangeRates($current_currency);
        // Fetch target currency exchange rates
        ExchangeRate::storeExchangeRates($target_currency);
        // Convert
        return $this->getConvertedMoney($money, $target_currency);
    }

    private function getConvertedMoneyUsingHistoricalExchangeRates(Money $money, $target_currency, $date_time): int
    {
        if (! ExchangeRate::isSupportHistoricalExchangeRate()) {
            Log::error(self::LOG_PREFIX . ' Historical exchange rate is not supported for the exchange rate service');
            throw new MoneyConversionException(self::LOG_PREFIX . " Historical exchange rate is not supported for the exchange rate service");
        }
        $current_currency = $money->getCurrency()->getCurrencyCode();

        if (!$this->extrapolate) {
            // Fetch base currency exchange rates
            // Hit the API to get the historical exchange rate if not found in the database
            $historicalExchangeRate = HistoricalExchangeRate::getHistoricalExchangeRate($current_currency, $target_currency, $date_time);
            $normalizedRate = $this->normalizeRate($historicalExchangeRate->exchange_rate);

            return $money->multipliedBy($normalizedRate, $this->rounding_mode)
                ->getMinorAmount()
                ->toInt();
        }

        try {
            // Try query historical exchange rate from database
            $historical_exchange_rate = ExchangeRateRepository::getHistoricalExchangeRate($current_currency, $target_currency, $date_time);
            $normalizedRate = $this->normalizeRate($historical_exchange_rate->exchange_rate);

            return $money->multipliedBy($normalizedRate, $this->rounding_mode)
                ->getMinorAmount()
                ->toInt();
        } catch (ModelNotFoundException $e) {
            // Try extrapolate with proxy currency
            $proxy_currency = Config::get('money-converter.extrapolate_currency_code', 'USD');

            $proxy_currency_exchange_rates = ExchangeRateRepository::getHistoricalExchangeRates($proxy_currency, $date_time);
            $proxy_target_rate = $proxy_currency_exchange_rates->where('target_currency_code', $target_currency)->firstOrFail();
            $proxy_current_rate = $proxy_currency_exchange_rates->where('target_currency_code', $current_currency)->firstOrFail();

            $proxyTarget = BigDecimal::of($proxy_target_rate->exchange_rate);
            $proxyCurrent = BigDecimal::of($proxy_current_rate->exchange_rate);
            $current_target_rate = $proxyTarget->dividedBy(
                $proxyCurrent,
                8,
                $this->rounding_mode
            );

            $normalizedRate = $this->normalizeRate($current_target_rate);

            return $money->multipliedBy($normalizedRate, $this->rounding_mode)
                ->getMinorAmount()
                ->toInt();
        }
    }

    /**
     * Normalize an exchange rate to a fixed scale using the configured rounding mode.
     *
     * @param mixed $rate
     */
    private function normalizeRate(mixed $rate): string
    {
        $decimal = $rate instanceof BigDecimal
            ? $rate
            : BigDecimal::of((string) $rate);

        return (string) $decimal->toScale(8, $this->rounding_mode);
    }

    private function logError(\Throwable $th, array $func_args, string $method_name): void
    {
        Log::error(self::LOG_PREFIX . ' Error: ' . $th->getMessage() . ' in ' . $th->getFile() . ' on line ' . $th->getLine() . ' in method ' . $method_name);
        Log::error(self::LOG_PREFIX . ' Method args: ' . json_encode($func_args));
        Log::error(self::LOG_PREFIX . ' Exception class: ' . get_class($th));
        Log::debug(self::LOG_PREFIX . ' Trace: ' . $th->getTraceAsString());
    }
}
