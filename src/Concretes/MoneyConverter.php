<?php

namespace BrightCreations\MoneyConverter\Concretes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\CurrencyConverter;
use Brick\Money\Money;
use BrightCreations\ExchangeRates\DTOs\CurrenciesPairDto;
use BrightCreations\ExchangeRates\DTOs\HistoricalCurrenciesPairDto;
use BrightCreations\ExchangeRates\Facades\ExchangeRate;
use BrightCreations\ExchangeRates\Facades\ExchangeRateRepository;
use BrightCreations\ExchangeRates\Facades\HistoricalExchangeRate;
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use BrightCreations\MoneyConverter\DTOs\ConversionItem;
use BrightCreations\MoneyConverter\DTOs\MoneyConversionResult;
use BrightCreations\MoneyConverter\DTOs\MoneyConverterOptions;
use BrightCreations\MoneyConverter\Enums\ConversionStrategy;
use BrightCreations\MoneyConverter\Events\ConversionPerformed;
use BrightCreations\MoneyConverter\Exceptions\MoneyConversionException;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final class MoneyConverter implements MoneyConverterInterface
{
    private const LOG_PREFIX = '[MoneyConverter]';

    private const INTERMEDIATE_RATE_SCALE = 10;

    private const INTERMEDIATE_ROUNDING = RoundingMode::HALF_UP;

    private MoneyConverterOptions $options;

    private RoundingMode $rounding_mode;

    public function __construct(
        private readonly CurrencyConverter $converter
    ) {
        $this->options = MoneyConverterOptions::fromConfig(Config::get('money-converter.default_options'));
        $this->rounding_mode = Config::get('money-converter.rounding_mode', RoundingMode::DOWN);
    }

    public function withOptions(MoneyConverterOptions $options): static
    {
        $clone = clone $this;
        $clone->options = $options;

        return $clone;
    }

    public function extrapolate(bool $extrapolate = true): static
    {
        return $this->withOptions($this->options->withExtrapolate($extrapolate));
    }

    public function interpolate(bool $interpolate = true): static
    {
        return $this->withOptions($this->options->withInterpolate($interpolate));
    }

    public function needsFresh(bool $needs_fresh = true): static
    {
        return $this->withOptions($this->options->withNeedsFresh($needs_fresh));
    }

    public function throwOnFail(): static
    {
        return $this->withOptions($this->options->withThrowOnFail());
    }

    public function fetchOnFail(): static
    {
        return $this->withOptions($this->options->withFetchOnFail());
    }

    public function isFetchOnFail(): bool
    {
        return $this->options->onFail === static::ON_FAIL_FETCH_EXCHANGE_RATES;
    }

    public function isThrowOnFail(): bool
    {
        return $this->options->onFail === static::ON_FAIL_THROW_EXCEPTION;
    }

    public function convert(int $money, string $current_currency, string $target_currency, ?CarbonInterface $date_time = null, ?int $on_fail = null, ?MoneyConverterOptions $options = null): int
    {
        return $this->convertWithResult($money, $current_currency, $target_currency, $date_time, $on_fail, $options)->amount;
    }

    public function convertWithResult(int $money, string $current_currency, string $target_currency, ?CarbonInterface $date_time = null, ?int $on_fail = null, ?MoneyConverterOptions $options = null): MoneyConversionResult
    {
        if ($current_currency === $target_currency) {
            $result = ResolvedConversion::sameCurrency($money)->toResult();
            $this->notifyConversionPerformed($money, $current_currency, $target_currency, $date_time, $result);

            return $result;
        }

        $instance = $this->resolveInstanceForCall($on_fail, $options);

        if ($date_time) {
            $result = $instance->resolveHistoricalConversion(
                Money::ofMinor($money, $current_currency),
                $target_currency,
                $date_time
            )->toResult();
            $instance->notifyConversionPerformed($money, $current_currency, $target_currency, $date_time, $result);

            return $result;
        }

        if ($instance->options->needsFresh) {
            $result = $instance->resolveFreshConversion(
                Money::ofMinor($money, $current_currency),
                $target_currency
            )->toResult();
            $instance->notifyConversionPerformed($money, $current_currency, $target_currency, null, $result);

            return $result;
        }

        $result = $instance->resolveCurrentConversion(
            Money::ofMinor($money, $current_currency),
            $target_currency
        )->toResult();
        $instance->notifyConversionPerformed($money, $current_currency, $target_currency, null, $result);

        return $result;
    }

    public function convertBulk(array $items, ?MoneyConverterOptions $options = null): array
    {
        return array_map(
            fn (MoneyConversionResult $result) => $result->amount,
            $this->convertBulkWithResult($items, $options)
        );
    }

    public function convertBulkWithResult(array $items, ?MoneyConverterOptions $options = null): array
    {
        if ($items === []) {
            return [];
        }

        $instance = $options !== null ? $this->withOptions($options) : $this;

        if ($instance->options->needsFresh) {
            $currencies = [];
            foreach ($items as $item) {
                if ($item->from !== $item->to) {
                    $currencies[] = $item->from;
                    $currencies[] = $item->to;
                }
            }

            if ($currencies !== []) {
                ExchangeRate::storeBulkExchangeRatesForMultipleCurrencies(array_values(array_unique($currencies)));
            }
        }

        $currentPairDtos = [];
        $historicalPairDtos = [];

        foreach ($items as $item) {
            if ($item->from === $item->to || $instance->options->needsFresh) {
                continue;
            }

            if ($item->date === null) {
                $currentPairDtos[(string) new CurrenciesPairDto($item->from, $item->to)] = new CurrenciesPairDto($item->from, $item->to);
            } else {
                $historicalPairDtos[(string) new HistoricalCurrenciesPairDto($item->from, $item->to, $item->date)] = new HistoricalCurrenciesPairDto($item->from, $item->to, $item->date);
            }
        }

        $currentRates = $currentPairDtos === []
            ? collect()
            : ExchangeRateRepository::getBulkExchangeRate(array_values($currentPairDtos));

        $historicalRates = $historicalPairDtos === []
            ? collect()
            : ExchangeRateRepository::getBulkHistoricalExchangeRate(array_values($historicalPairDtos));

        $boundingCache = collect();

        if ($instance->options->interpolate && $historicalPairDtos !== []) {
            $boundingDtos = [];

            foreach ($items as $item) {
                if ($item->from === $item->to || $item->date === null || $instance->options->needsFresh) {
                    continue;
                }

                if ($this->hasExactHistoricalPrefetch($item, $historicalRates)) {
                    continue;
                }

                $dto = new HistoricalCurrenciesPairDto($item->from, $item->to, $item->date);
                $boundingDtos[(string) $dto] = $dto;
            }

            if ($boundingDtos !== []) {
                $boundingCache = ExchangeRateRepository::getBulkBoundingHistoricalRates(array_values($boundingDtos));
            }
        }

        $results = [];

        foreach ($items as $item) {
            $result = $instance->resolveBulkItem($item, $currentRates, $historicalRates, $boundingCache)->toResult();
            $instance->notifyConversionPerformed($item->amount, $item->from, $item->to, $item->date, $result);
            $results[] = $result;
        }

        return $results;
    }

    public function convertCurrent(int $money, string $current_currency, string $target_currency, ?MoneyConverterOptions $options = null): int
    {
        $instance = $options !== null ? $this->withOptions($options) : $this;

        if ($current_currency === $target_currency) {
            $result = ResolvedConversion::sameCurrency($money)->toResult();
            $instance->notifyConversionPerformed($money, $current_currency, $target_currency, null, $result);

            return $money;
        }

        $moneyObject = Money::ofMinor($money, $current_currency);
        try {
            $result = $instance->resolveCurrentConversion($moneyObject, $target_currency)->toResult();
            $instance->notifyConversionPerformed($money, $current_currency, $target_currency, null, $result);

            return $result->amount;
        } catch (\Throwable $th) {
            $this->logError($th, func_get_args(), __METHOD__);
            throw new MoneyConversionException(self::LOG_PREFIX.' Error while converting currency with current exchange rates: '.$th->getMessage(), $th);
        }
    }

    public function convertFresh(int $money, string $current_currency, string $target_currency, ?MoneyConverterOptions $options = null): int
    {
        $instance = $options !== null ? $this->withOptions($options) : $this;

        if ($current_currency === $target_currency) {
            $result = ResolvedConversion::sameCurrency($money)->toResult();
            $instance->notifyConversionPerformed($money, $current_currency, $target_currency, null, $result);

            return $money;
        }

        $moneyObject = Money::ofMinor($money, $current_currency);
        try {
            $result = $instance->resolveFreshConversion($moneyObject, $target_currency)->toResult();
            $instance->notifyConversionPerformed($money, $current_currency, $target_currency, null, $result);

            return $result->amount;
        } catch (\Throwable $th) {
            $this->logError($th, func_get_args(), __METHOD__);
            throw new MoneyConversionException(self::LOG_PREFIX.' Error while converting currency using fresh exchange rates: '.$th->getMessage(), $th);
        }
    }

    public function convertHistorical(int $money, string $current_currency, string $target_currency, CarbonInterface $date_time, ?MoneyConverterOptions $options = null): int
    {
        $instance = $options !== null ? $this->withOptions($options) : $this;

        if ($current_currency === $target_currency) {
            $result = ResolvedConversion::sameCurrency($money)->toResult();
            $instance->notifyConversionPerformed($money, $current_currency, $target_currency, $date_time, $result);

            return $money;
        }

        $moneyObject = Money::ofMinor($money, $current_currency);
        try {
            $result = $instance->resolveHistoricalConversion($moneyObject, $target_currency, $date_time)->toResult();
            $instance->notifyConversionPerformed($money, $current_currency, $target_currency, $date_time, $result);

            return $result->amount;
        } catch (\Throwable $th) {
            $this->logError($th, func_get_args(), __METHOD__);
            throw new MoneyConversionException(self::LOG_PREFIX.' Error while converting currency using historical exchange rates: '.$th->getMessage(), $th);
        }
    }

    public function convertToday(int $money, string $current_currency, string $target_currency, ?MoneyConverterOptions $options = null): int
    {
        $instance = $options !== null ? $this->withOptions($options) : $this;
        $date = Carbon::now()->startOfDay();

        if ($current_currency === $target_currency) {
            $result = ResolvedConversion::sameCurrency($money)->toResult();
            $instance->notifyConversionPerformed($money, $current_currency, $target_currency, $date, $result);

            return $money;
        }

        $moneyObject = Money::ofMinor($money, $current_currency);
        try {
            $result = $instance->resolveHistoricalConversion($moneyObject, $target_currency, $date)->toResult();
            $instance->notifyConversionPerformed($money, $current_currency, $target_currency, $date, $result);

            return $result->amount;
        } catch (\Throwable $th) {
            $this->logError($th, func_get_args(), __METHOD__);
            throw new MoneyConversionException(self::LOG_PREFIX." Error while converting currency using today's exchange rates: ".$th->getMessage(), $th);
        }
    }

    private function resolveBulkItem(ConversionItem $item, Collection $currentRates, Collection $historicalRates, ?Collection $boundingCache = null): ResolvedConversion
    {
        if ($item->from === $item->to) {
            return ResolvedConversion::sameCurrency($item->amount);
        }

        $money = Money::ofMinor($item->amount, $item->from);

        if (! $this->options->needsFresh && $item->date === null) {
            $pairKey = $item->from.'_'.$item->to;
            $prefetched = $currentRates->get($pairKey)?->first();

            if ($prefetched !== null) {
                return ResolvedConversion::fromRate(
                    $money,
                    $prefetched->exchange_rate,
                    ConversionStrategy::ExactDb,
                    $prefetched->provider,
                    $this->rounding_mode,
                );
            }
        }

        if (! $this->options->needsFresh && $item->date !== null) {
            $prefetched = $historicalRates
                ->get($item->from)
                ?->get($item->to)
                ?->get($item->date->format('Y-m-d'))
                ?->first();

            if ($prefetched !== null) {
                return ResolvedConversion::fromRate(
                    $money,
                    $prefetched->exchange_rate,
                    ConversionStrategy::ExactDb,
                    $prefetched->provider,
                    $this->rounding_mode,
                );
            }
        }

        if ($item->date !== null) {
            return $this->resolveHistoricalConversion($money, $item->to, $item->date, $boundingCache);
        }

        if ($this->options->needsFresh) {
            return $this->resolveFreshConversion($money, $item->to);
        }

        return $this->resolveCurrentConversion($money, $item->to);
    }

    private function resolveCurrentConversion(Money $money, string $target_currency): ResolvedConversion
    {
        $current_currency = $money->getCurrency()->getCurrencyCode();

        try {
            $rateModel = ExchangeRateRepository::getExchangeRate($current_currency, $target_currency);

            return ResolvedConversion::fromRate(
                $money,
                $rateModel->exchange_rate,
                ConversionStrategy::ExactDb,
                $rateModel->provider,
                $this->rounding_mode,
            );
        } catch (ModelNotFoundException) {
            if ($this->isFetchOnFail()) {
                return $this->resolveFreshConversion($money, $target_currency);
            }

            throw new MoneyConversionException(self::LOG_PREFIX." Unable to find exchange rate for {$current_currency} to {$target_currency}");
        }
    }

    private function resolveFreshConversion(Money $money, string $target_currency): ResolvedConversion
    {
        $resolved = $this->getConvertedMoneyUsingFreshExchangeRates($money, $target_currency);
        $current_currency = $money->getCurrency()->getCurrencyCode();

        try {
            $rateModel = ExchangeRateRepository::getExchangeRate($current_currency, $target_currency);

            return new ResolvedConversion(
                $resolved->amount,
                (string) $rateModel->exchange_rate,
                ConversionStrategy::Fresh,
                $rateModel->provider,
            );
        } catch (ModelNotFoundException) {
            return new ResolvedConversion($resolved->amount, $resolved->rate, ConversionStrategy::Fresh);
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

    private function getConvertedMoneyUsingFreshExchangeRates(Money $money, string $target_currency): ResolvedConversion
    {
        $current_currency = $money->getCurrency()->getCurrencyCode();
        ExchangeRate::storeBulkExchangeRatesForMultipleCurrencies([$current_currency, $target_currency]);

        $amount = $this->getConvertedMoney($money, $target_currency);

        try {
            $rateModel = ExchangeRateRepository::getExchangeRate($current_currency, $target_currency);

            return new ResolvedConversion(
                $amount,
                (string) $rateModel->exchange_rate,
                ConversionStrategy::Fresh,
                $rateModel->provider,
            );
        } catch (ModelNotFoundException) {
            return new ResolvedConversion($amount, '0', ConversionStrategy::Fresh);
        }
    }

    private function resolveHistoricalConversion(Money $money, string $target_currency, CarbonInterface $date_time, ?Collection $boundingCache = null): ResolvedConversion
    {
        if (! ExchangeRate::isSupportHistoricalExchangeRate()) {
            Log::error(self::LOG_PREFIX.' Historical exchange rate is not supported for the exchange rate service');
            throw new MoneyConversionException(self::LOG_PREFIX.' Historical exchange rate is not supported for the exchange rate service');
        }

        $current_currency = $money->getCurrency()->getCurrencyCode();

        try {
            $historical_exchange_rate = ExchangeRateRepository::getHistoricalExchangeRate($current_currency, $target_currency, $date_time);

            return ResolvedConversion::fromRate(
                $money,
                $historical_exchange_rate->exchange_rate,
                ConversionStrategy::ExactDb,
                $historical_exchange_rate->provider,
                $this->rounding_mode,
            );
        } catch (ModelNotFoundException) {
            // Continue to next step
        }

        if ($this->isFetchOnFail()) {
            if (! ExchangeRate::supportsHistoricalApiFetch()) {
                Log::debug(self::LOG_PREFIX.' fetchOnFail is enabled but the configured provider does not auto-fetch historical rates on miss');
            }

            try {
                $historicalExchangeRate = HistoricalExchangeRate::getHistoricalExchangeRate($current_currency, $target_currency, $date_time);

                return ResolvedConversion::fromRate(
                    $money,
                    $historicalExchangeRate->exchange_rate,
                    ConversionStrategy::ApiFetch,
                    $historicalExchangeRate->provider,
                    $this->rounding_mode,
                );
            } catch (\Throwable $e) {
                Log::debug(self::LOG_PREFIX.' API fetch failed, continuing to next strategy: '.$e->getMessage());
            }
        } else {
            Log::debug(self::LOG_PREFIX.' API fetch is not enabled, continuing to next strategy');
        }

        try {
            $proxy_currency = Config::get('money-converter.proxy_currency_code', 'USD');

            if ($this->isFetchOnFail()) {
                $proxy_currency_exchange_rates = HistoricalExchangeRate::getHistoricalExchangeRates($proxy_currency, $date_time);
            } else {
                $proxy_currency_exchange_rates = ExchangeRateRepository::getHistoricalExchangeRates($proxy_currency, $date_time);
            }
            $proxy_target_rate = $proxy_currency_exchange_rates->where('target_currency_code', $target_currency)->firstOrFail();
            $proxy_current_rate = $proxy_currency_exchange_rates->where('target_currency_code', $current_currency)->firstOrFail();

            $proxyTarget = BigDecimal::of($proxy_target_rate->exchange_rate);
            $proxyCurrent = BigDecimal::of($proxy_current_rate->exchange_rate);
            $current_target_rate = $proxyTarget->dividedBy(
                $proxyCurrent,
                self::INTERMEDIATE_RATE_SCALE,
                self::INTERMEDIATE_ROUNDING
            );

            return ResolvedConversion::fromRate(
                $money,
                $current_target_rate,
                ConversionStrategy::Proxy,
                $proxy_target_rate->provider,
                $this->rounding_mode,
            );
        } catch (\Throwable $e) {
            Log::debug(self::LOG_PREFIX.' Proxy-currency strategy failed, continuing to next strategy: '.$e->getMessage());
        }

        if ($this->options->interpolate) {
            try {
                $result = $this->tryInterpolation($money, $current_currency, $target_currency, $date_time, $boundingCache);
                if ($result !== null) {
                    return $result;
                }
            } catch (\Throwable $e) {
                Log::debug(self::LOG_PREFIX.' Interpolation failed, continuing to next strategy: '.$e->getMessage());
            }
        }

        if ($this->options->extrapolate) {
            try {
                $result = $this->tryExtrapolation($money, $current_currency, $target_currency, $date_time);
                if ($result !== null) {
                    return $result;
                }
            } catch (\Throwable $e) {
                Log::debug(self::LOG_PREFIX.' Extrapolation failed, continuing to next strategy: '.$e->getMessage());
            }
        }

        throw new MoneyConversionException(self::LOG_PREFIX." Unable to find or compute exchange rate for {$current_currency} to {$target_currency} on {$date_time->toDateString()}");
    }

    private function tryInterpolation(Money $money, string $current_currency, string $target_currency, CarbonInterface $date_time, ?Collection $boundingCache = null): ?ResolvedConversion
    {
        $cacheKey = $current_currency.'_'.$target_currency.'_'.$date_time->format('Y-m-d');

        if ($boundingCache !== null && $boundingCache->has($cacheKey)) {
            $boundingRates = $boundingCache->get($cacheKey);
        } else {
            $boundingRates = ExchangeRateRepository::getBoundingHistoricalRates($current_currency, $target_currency, $date_time);
        }

        if ($boundingRates->count() !== 2) {
            Log::debug(self::LOG_PREFIX.' Interpolation requires exactly 2 bounding rates, got: '.$boundingRates->count());

            return null;
        }

        $d1 = $boundingRates->first();
        $d2 = $boundingRates->last();

        $t1 = $d1->date_time instanceof CarbonInterface ? $d1->date_time : Carbon::parse($d1->date_time);
        $t2 = $d2->date_time instanceof CarbonInterface ? $d2->date_time : Carbon::parse($d2->date_time);
        $r1 = $this->normalizeRate($d1->exchange_rate);
        $r2 = $this->normalizeRate($d2->exchange_rate);

        $t = $date_time->diffInSeconds($t1, false);
        $T = $t2->diffInSeconds($t1, false);

        if ($T == 0) {
            Log::debug(self::LOG_PREFIX.' Interpolation failed: time difference between bounds is zero');

            return null;
        }

        $fraction = BigDecimal::of($t)->dividedBy(BigDecimal::of($T), 16, self::INTERMEDIATE_ROUNDING);
        $rateDiff = $r2->minus($r1);
        $interpolatedRate = $r1->plus($fraction->multipliedBy($rateDiff));

        Log::info(self::LOG_PREFIX." Interpolated rate for {$current_currency}/{$target_currency} on {$date_time->toDateString()}: {$interpolatedRate} (between {$t1->toDateString()} @ {$r1} and {$t2->toDateString()} @ {$r2})");

        return ResolvedConversion::fromRate(
            $money,
            $interpolatedRate,
            ConversionStrategy::Interpolate,
            $d1->provider ?? $d2->provider,
            $this->rounding_mode,
        );
    }

    private function tryExtrapolation(Money $money, string $current_currency, string $target_currency, CarbonInterface $date_time): ?ResolvedConversion
    {
        $previous = ExchangeRateRepository::getPreviousHistoricalRate($current_currency, $target_currency, $date_time);
        $next = ExchangeRateRepository::getNextHistoricalRate($current_currency, $target_currency, $date_time);

        if ($previous && ! $next) {
            $rate = $this->normalizeRate($previous->exchange_rate);
            Log::info(self::LOG_PREFIX." Extrapolated (nearest previous) rate for {$current_currency}/{$target_currency} on {$date_time->toDateString()}: {$rate}");

            return ResolvedConversion::fromRate(
                $money,
                $rate,
                ConversionStrategy::Extrapolate,
                $previous->provider,
                $this->rounding_mode,
            );
        }

        if ($next && ! $previous) {
            $rate = $this->normalizeRate($next->exchange_rate);
            Log::info(self::LOG_PREFIX." Extrapolated (nearest next) rate for {$current_currency}/{$target_currency} on {$date_time->toDateString()}: {$rate}");

            return ResolvedConversion::fromRate(
                $money,
                $rate,
                ConversionStrategy::Extrapolate,
                $next->provider,
                $this->rounding_mode,
            );
        }

        Log::debug(self::LOG_PREFIX.' Extrapolation skipped: requires exactly one of previous/next bounds');

        return null;
    }

    private function hasExactHistoricalPrefetch(ConversionItem $item, Collection $historicalRates): bool
    {
        if ($item->date === null) {
            return false;
        }

        return $historicalRates
            ->get($item->from)
            ?->get($item->to)
            ?->get($item->date->format('Y-m-d'))
            ?->first() !== null;
    }

    private function resolveInstanceForCall(?int $on_fail, ?MoneyConverterOptions $options): self
    {
        $instance = $this;

        if ($options !== null) {
            $instance = $instance->withOptions($options);
        }

        if ($on_fail !== null) {
            $instance = $instance->withOptions($instance->options->withOnFail($on_fail));
        }

        return $instance;
    }

    private function notifyConversionPerformed(
        int $inputAmount,
        string $from,
        string $to,
        ?CarbonInterface $date,
        MoneyConversionResult $result,
    ): void {
        if (! Config::get('money-converter.events.dispatch_conversion_performed', true)) {
            return;
        }

        ConversionPerformed::dispatch($inputAmount, $from, $to, $date, $result);
    }

    private function normalizeRate(mixed $rate): BigDecimal
    {
        return $rate instanceof BigDecimal
            ? $rate
            : BigDecimal::of((string) $rate);
    }

    private function logError(\Throwable $th, array $func_args, string $method_name): void
    {
        Log::error(self::LOG_PREFIX.' Error: '.$th->getMessage().' in '.$th->getFile().' on line '.$th->getLine().' in method '.$method_name);
        Log::error(self::LOG_PREFIX.' Method args: '.json_encode($func_args));
        Log::error(self::LOG_PREFIX.' Exception class: '.get_class($th));
        Log::debug(self::LOG_PREFIX.' Trace: '.$th->getTraceAsString());
    }
}
