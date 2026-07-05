<?php

use BrightCreations\ExchangeRates\Facades\HistoricalExchangeRate;
use BrightCreations\ExchangeRates\Models\CurrencyExchangeRateHistory;
use BrightCreations\MoneyConverter\Enums\ConversionStrategy;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;

uses(SeedsExchangeRates::class);

beforeEach(function () {
    $this->converter = $this->makeMoneyConverter();
});

it('fetches historical rates from the api when fetchOnFail is enabled and db has no exact match', function () {
    $date = Carbon::parse('2024-06-01')->startOfDay();

    $fetched = new CurrencyExchangeRateHistory([
        'base_currency_code' => 'USD',
        'target_currency_code' => 'EUR',
        'exchange_rate' => '0.87',
        'provider' => 'test-provider',
        'date_time' => $date,
    ]);

    HistoricalExchangeRate::shouldReceive('getHistoricalExchangeRate')
        ->once()
        ->with('USD', 'EUR', $date)
        ->andReturn($fetched);

    $result = $this->converter
        ->fetchOnFail()
        ->convertWithResult(10000, 'USD', 'EUR', $date);

    expect($result->amount)->toBe(8700);
    expect($result->strategy)->toBe(ConversionStrategy::ApiFetch);
    expect($result->rate)->toBe('0.87');
    expect($result->provider)->toBe('test-provider');
});
