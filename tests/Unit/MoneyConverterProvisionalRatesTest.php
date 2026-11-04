<?php

use BrightCreations\ExchangeRates\Facades\HistoricalExchangeRate;
use BrightCreations\ExchangeRates\Models\CurrencyExchangeRateHistory;
use BrightCreations\MoneyConverter\DTOs\ConversionItem;
use BrightCreations\MoneyConverter\DTOs\MoneyConverterOptions;
use BrightCreations\MoneyConverter\Enums\ConversionStrategy;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;

uses(SeedsExchangeRates::class);

const PROVISIONAL_PROVIDER = 'WorldBankExchangeRateApiService';
const DAILY_RATE_PROVIDER = 'OpenExchangeRateService';

beforeEach(function () {
    if (! method_exists(CurrencyExchangeRateHistory::class, 'isProvisional')) {
        $this->markTestSkipped('Installed brightcreations/exchange-rates has no provisional rates');
    }

    Carbon::setTestNow(Carbon::parse('2026-11-04 12:00:00', 'UTC'));
    $this->converter = $this->makeMoneyConverter();
    $this->date = Carbon::parse('2024-03-10');
});

afterEach(function () {
    Carbon::setTestNow();
});

function seedRate(string $base, string $target, float $rate, string $date, string $provider): void
{
    test()->repository()->updateExchangeRatesHistory($base, [$target => $rate], Carbon::parse($date), $provider);
}

it('prefers interpolating daily rates over a provisional rate for the date', function () {
    seedRate('USD', 'EUR', 0.80, '2024-03-10', PROVISIONAL_PROVIDER);
    seedRate('USD', 'EUR', 0.90, '2024-03-09', DAILY_RATE_PROVIDER);
    seedRate('USD', 'EUR', 0.92, '2024-03-11', DAILY_RATE_PROVIDER);

    $result = $this->converter->interpolate()->convertWithResult(10000, 'USD', 'EUR', $this->date);

    expect($result->strategy)->toBe(ConversionStrategy::Interpolate);
    expect($result->amount)->toBe(9100);
});

it('prefers a daily cross-rate over a provisional rate for the date', function () {
    seedRate('EUR', 'GBP', 0.50, '2024-03-10', PROVISIONAL_PROVIDER);
    seedRate('USD', 'EUR', 0.80, '2024-03-10', DAILY_RATE_PROVIDER);
    seedRate('USD', 'GBP', 0.60, '2024-03-10', DAILY_RATE_PROVIDER);

    $result = $this->converter->convertWithResult(10000, 'EUR', 'GBP', $this->date);

    expect($result->strategy)->toBe(ConversionStrategy::Proxy);
    expect($result->amount)->toBe(7500);
});

it('uses the provisional rate before extrapolating from a single daily rate', function () {
    seedRate('USD', 'EUR', 0.80, '2024-03-10', PROVISIONAL_PROVIDER);
    seedRate('USD', 'EUR', 0.95, '2026-01-01', DAILY_RATE_PROVIDER);

    $result = $this->converter->interpolate()->extrapolate()->convertWithResult(10000, 'USD', 'EUR', $this->date);

    expect($result->strategy)->toBe(ConversionStrategy::ExactDb);
    expect($result->provider)->toBe(PROVISIONAL_PROVIDER);
    expect($result->amount)->toBe(8000);
});

it('does not ask the providers again for a date that already has a provisional rate', function () {
    seedRate('USD', 'EUR', 0.80, '2024-03-10', PROVISIONAL_PROVIDER);
    HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRate')->never();
    HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRates')->andReturnUsing(
        fn (string $base, $date_time) => $this->repository()->getHistoricalExchangeRates($base, $date_time)
    );

    $result = $this->converter->fetchOnFail()->convertWithResult(10000, 'USD', 'EUR', $this->date);

    expect($result->provider)->toBe(PROVISIONAL_PROVIDER);
});

it('keeps a freshly fetched provisional rate as a last resort', function () {
    seedRate('USD', 'EUR', 0.90, '2024-03-09', DAILY_RATE_PROVIDER);
    seedRate('USD', 'EUR', 0.92, '2024-03-11', DAILY_RATE_PROVIDER);
    HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRate')->once()->andReturn(new CurrencyExchangeRateHistory([
        'base_currency_code' => 'USD',
        'target_currency_code' => 'EUR',
        'exchange_rate' => '0.80',
        'provider' => PROVISIONAL_PROVIDER,
        'date_time' => $this->date,
    ]));
    HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRates')->andReturn(collect());

    $result = $this->converter->fetchOnFail()->interpolate()->convertWithResult(10000, 'USD', 'EUR', $this->date);

    expect($result->strategy)->toBe(ConversionStrategy::Interpolate);
});

it('extrapolates from the daily side when the other side is provisional', function () {
    seedRate('USD', 'EUR', 0.80, '2024-03-01', PROVISIONAL_PROVIDER);
    seedRate('USD', 'EUR', 0.95, '2024-03-20', DAILY_RATE_PROVIDER);

    $result = $this->converter->interpolate()->extrapolate()->convertWithResult(10000, 'USD', 'EUR', $this->date);

    expect($result->strategy)->toBe(ConversionStrategy::Extrapolate);
    expect($result->provider)->toBe(DAILY_RATE_PROVIDER);
    expect($result->amount)->toBe(9500);
});

it('runs a prefetched provisional rate in a bulk conversion through the full chain', function () {
    seedRate('USD', 'EUR', 0.80, '2024-03-10', PROVISIONAL_PROVIDER);
    seedRate('USD', 'EUR', 0.90, '2024-03-09', DAILY_RATE_PROVIDER);
    seedRate('USD', 'EUR', 0.92, '2024-03-11', DAILY_RATE_PROVIDER);

    $results = $this->converter->convertBulkWithResult(
        [new ConversionItem(10000, 'USD', 'EUR', $this->date)],
        MoneyConverterOptions::defaults()->withInterpolate(),
    );

    expect($results[0]->strategy)->toBe(ConversionStrategy::Interpolate);
});
