<?php

use BrightCreations\ExchangeRates\Facades\ExchangeRate;
use BrightCreations\ExchangeRates\Facades\HistoricalExchangeRate;
use BrightCreations\ExchangeRates\Models\CurrencyExchangeRateHistory;
use BrightCreations\MoneyConverter\DTOs\ConversionItem;
use BrightCreations\MoneyConverter\DTOs\MoneyConverterOptions;
use BrightCreations\MoneyConverter\Enums\ConversionStrategy;
use BrightCreations\MoneyConverter\Exceptions\MoneyConversionException;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;
use Illuminate\Support\Collection;

uses(SeedsExchangeRates::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-04 12:00:00', 'UTC'));
    $this->converter = $this->makeMoneyConverter();
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('Future dates', function () {
    it('does not ask the provider for rates on a future date', function () {
        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRate')->never();
        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRates')->never();

        $this->converter->fetchOnFail()->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2026-12-15'));
    })->throws(MoneyConversionException::class);

    it('still resolves a future date from stored rates', function () {
        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRate')->never();
        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRates')->never();
        $this->seedHistoricalRate('USD', 'EUR', 0.9, Carbon::parse('2026-11-01'));

        $result = $this->converter
            ->fetchOnFail()
            ->extrapolate()
            ->convertWithResult(10000, 'USD', 'EUR', Carbon::parse('2026-12-15'));

        expect($result->amount)->toBe(9000);
        expect($result->strategy)->toBe(ConversionStrategy::Extrapolate);
    });

    it('still reads stored proxy rates for a future date', function () {
        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRates')->never();
        $date = Carbon::parse('2026-12-15');
        $this->seedProxyRatesForDate($date);

        $result = $this->converter->fetchOnFail()->convertWithResult(10000, 'EUR', 'GBP', $date);

        expect($result->strategy)->toBe(ConversionStrategy::Proxy);
    });

    it('still asks the provider for today (UTC)', function () {
        $date = Carbon::parse('2026-11-04');
        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRate')
            ->once()
            ->andReturn(new CurrencyExchangeRateHistory([
                'base_currency_code' => 'USD',
                'target_currency_code' => 'EUR',
                'exchange_rate' => '0.87',
                'provider' => 'test-provider',
                'date_time' => $date,
            ]));

        $result = $this->converter->fetchOnFail()->convertWithResult(10000, 'USD', 'EUR', $date);

        expect($result->strategy)->toBe(ConversionStrategy::ApiFetch);
    });
});

describe('Bulk conversions', function () {
    it('does not repeat a failed provider fetch for the same currency and date', function () {
        $date = Carbon::parse('2026-10-15 12:00:00');
        foreach (['EUR' => [0.90, 0.92], 'GBP' => [0.80, 0.82]] as $target => [$before, $after]) {
            $this->seedHistoricalRate('USD', $target, $before, Carbon::parse('2026-10-14 12:00:00'));
            $this->seedHistoricalRate('USD', $target, $after, Carbon::parse('2026-10-16 12:00:00'));
        }

        // USD is also the proxy currency, so its proxy lookup must not refetch either
        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRate')->once()->andThrow(new RuntimeException('All exchange rate services failed'));
        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRates')->never();

        $results = $this->converter->convertBulkWithResult([
            new ConversionItem(10000, 'USD', 'EUR', $date),
            new ConversionItem(10000, 'USD', 'GBP', $date),
        ], MoneyConverterOptions::defaults()->withFetchOnFail()->withInterpolate());

        expect($results[0]->strategy)->toBe(ConversionStrategy::Interpolate);
        expect($results[0]->amount)->toBe(9100);
        expect($results[1]->strategy)->toBe(ConversionStrategy::Interpolate);
        expect($results[1]->amount)->toBe(8100);
    });

    it('keeps fetching a different currency or date after a failure', function () {
        $date = Carbon::parse('2026-10-15');
        $this->seedProxyRatesForDate($date);
        $this->seedProxyRatesForDate($date->copy()->addDay());

        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRate')->twice()->andThrow(new RuntimeException('All exchange rate services failed'));
        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRates')->twice()->andReturnUsing(
            fn (string $base, $date_time) => $this->repository()->getHistoricalExchangeRates($base, $date_time)
        );

        $results = $this->converter->convertBulkWithResult([
            new ConversionItem(10000, 'EUR', 'GBP', $date),
            new ConversionItem(10000, 'EUR', 'GBP', $date->copy()->addDay()),
        ], MoneyConverterOptions::defaults()->withFetchOnFail());

        expect($results[0]->strategy)->toBe(ConversionStrategy::Proxy);
        expect($results[1]->strategy)->toBe(ConversionStrategy::Proxy);
    });

    it('does not carry failed fetches over to later conversions', function () {
        $date = Carbon::parse('2026-10-15');
        $this->seedProxyRatesForDate($date);

        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRate')->twice()->andThrow(new RuntimeException('All exchange rate services failed'));
        HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRates')->andReturnUsing(
            fn (string $base, $date_time) => $this->repository()->getHistoricalExchangeRates($base, $date_time)
        );

        $options = MoneyConverterOptions::defaults()->withFetchOnFail();
        $this->converter->convertBulkWithResult([new ConversionItem(10000, 'EUR', 'GBP', $date)], $options);

        expect($this->converter->convertWithResult(10000, 'EUR', 'GBP', $date, null, $options)->strategy)
            ->toBe(ConversionStrategy::Proxy);
    });

    it('refreshes only the source currencies when converting fresh', function () {
        ExchangeRate::shouldReceive('storeBulkExchangeRatesForMultipleCurrencies')
            ->once()
            ->with(['USD', 'EUR'])
            ->andReturnUsing(function () {
                $this->seedCurrentRate('USD', 'GBP', 0.75);
                $this->seedCurrentRate('EUR', 'GBP', 0.85);

                return new Collection;
            });

        $results = $this->converter->convertBulk([
            new ConversionItem(10000, 'USD', 'GBP'),
            new ConversionItem(10000, 'EUR', 'GBP'),
            new ConversionItem(10000, 'USD', 'GBP'),
        ], MoneyConverterOptions::defaults()->withNeedsFresh());

        expect($results)->toBe([7500, 8500, 7500]);
    });
});
