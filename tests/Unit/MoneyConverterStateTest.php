<?php

use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;

uses(SeedsExchangeRates::class);

it('does not mutate the singleton when fluent methods are called', function () {
    $singleton = app(MoneyConverterInterface::class);

    expect($singleton->isThrowOnFail())->toBeTrue();

    $singleton->extrapolate()->fetchOnFail()->interpolate();

    expect($singleton->isThrowOnFail())->toBeTrue();
    expect($singleton->isFetchOnFail())->toBeFalse();
});

it('does not mutate the singleton when convert is called with on_fail', function () {
    $singleton = app(MoneyConverterInterface::class);

    try {
        $singleton->convert(
            10000,
            'USD',
            'EUR',
            null,
            MoneyConverterInterface::ON_FAIL_FETCH_EXCHANGE_RATES
        );
    } catch (Throwable) {
        // Conversion may fail without seeded rates; state isolation is what we assert.
    }

    expect($singleton->isThrowOnFail())->toBeTrue();
    expect($singleton->isFetchOnFail())->toBeFalse();
});

it('applies fluent options on the returned clone', function () {
    $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));

    $singleton = app(MoneyConverterInterface::class);

    $result = $singleton
        ->extrapolate()
        ->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-06-01'));

    expect($result)->toBe(9000);
    expect($singleton->isThrowOnFail())->toBeTrue();
});
