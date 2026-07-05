<?php

use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use BrightCreations\MoneyConverter\DTOs\ConversionItem;
use BrightCreations\MoneyConverter\DTOs\MoneyConverterOptions;
use BrightCreations\MoneyConverter\Enums\ConversionStrategy;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;

uses(SeedsExchangeRates::class);

beforeEach(function () {
    $this->converter = $this->makeMoneyConverter();
});

it('interpolates using explicit options without a fluent chain', function () {
    $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));
    $this->seedHistoricalRate('USD', 'EUR', 0.95, Carbon::parse('2024-01-15'));

    $options = MoneyConverterOptions::defaults()->withInterpolate();
    $date = Carbon::parse('2024-01-08');

    $result = $this->converter->convertWithResult(10000, 'USD', 'EUR', $date, options: $options);

    expect($result->amount)->toBeGreaterThan(9000);
    expect($result->amount)->toBeLessThan(9500);
    expect($result->strategy)->toBe(ConversionStrategy::Interpolate);
});

it('applies per-call options without mutating the singleton', function () {
    $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));
    $this->seedHistoricalRate('USD', 'EUR', 0.95, Carbon::parse('2024-01-15'));

    $singleton = app(MoneyConverterInterface::class);
    $date = Carbon::parse('2024-01-08');
    $options = MoneyConverterOptions::defaults()->withInterpolate();

    $result = $singleton->convertWithResult(10000, 'USD', 'EUR', $date, options: $options);

    expect($result->strategy)->toBe(ConversionStrategy::Interpolate);
    expect($singleton->isThrowOnFail())->toBeTrue();
});

it('lets on_fail override options onFail for a single call', function () {
    $singleton = app(MoneyConverterInterface::class);
    $options = MoneyConverterOptions::defaults()->withFetchOnFail();

    try {
        $singleton->convert(
            10000,
            'USD',
            'EUR',
            null,
            MoneyConverterInterface::ON_FAIL_THROW_EXCEPTION,
            $options
        );
    } catch (Throwable) {
        // Expected without seeded rates when throw-on-fail wins.
    }

    expect($singleton->isThrowOnFail())->toBeTrue();
    expect($singleton->isFetchOnFail())->toBeFalse();
});

it('applies bulk options without a fluent chain', function () {
    $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));
    $this->seedHistoricalRate('USD', 'EUR', 0.95, Carbon::parse('2024-01-15'));

    $options = MoneyConverterOptions::defaults()->withInterpolate();

    $results = $this->converter->convertBulk([
        new ConversionItem(10000, 'USD', 'EUR', Carbon::parse('2024-01-05')),
        new ConversionItem(10000, 'USD', 'EUR', Carbon::parse('2024-01-10')),
    ], $options);

    foreach ($results as $amount) {
        expect($amount)->toBeGreaterThan(9000);
        expect($amount)->toBeLessThan(9500);
    }
});
