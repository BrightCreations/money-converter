<?php

use BrightCreations\MoneyConverter\DTOs\ConversionItem;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;

uses(SeedsExchangeRates::class);

beforeEach(function () {
    $this->converter = $this->makeMoneyConverter();
});

it('converts multiple current items in one batch', function () {
    $this->seedCurrentRate('USD', 'EUR', 0.85);

    $items = [
        new ConversionItem(10000, 'USD', 'EUR'),
        new ConversionItem(20000, 'USD', 'EUR'),
        new ConversionItem(5000, 'USD', 'EUR'),
    ];

    $results = $this->converter->convertBulk($items);

    expect($results)->toBe([8500, 17000, 4250]);
});

it('converts mixed current and historical items', function () {
    $this->seedCurrentRate('USD', 'GBP', 0.75);
    $date = Carbon::parse('2024-01-15')->startOfDay();
    $this->seedHistoricalRate('USD', 'EUR', 0.88, $date);

    $results = $this->converter->convertBulk([
        new ConversionItem(10000, 'USD', 'GBP'),
        new ConversionItem(10000, 'USD', 'EUR', $date),
    ]);

    expect($results[0])->toBe(7500);
    expect($results[1])->toBe(8800);
});

it('short-circuits same-currency items in bulk', function () {
    $results = $this->converter->convertBulk([
        new ConversionItem(10000, 'USD', 'USD'),
        new ConversionItem(5000, 'EUR', 'EUR'),
    ]);

    expect($results)->toBe([10000, 5000]);
});

it('falls through to interpolation for historical bulk items when exact rate is missing', function () {
    $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));
    $this->seedHistoricalRate('USD', 'EUR', 0.95, Carbon::parse('2024-01-15'));

    $results = $this->converter
        ->interpolate()
        ->convertBulk([
            new ConversionItem(10000, 'USD', 'EUR', Carbon::parse('2024-01-08')),
        ]);

    expect($results[0])->toBeGreaterThan(9000);
    expect($results[0])->toBeLessThan(9500);
});

it('interpolates multiple historical dates on the same pair in one bulk call', function () {
    $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));
    $this->seedHistoricalRate('USD', 'EUR', 0.95, Carbon::parse('2024-01-15'));

    $results = $this->converter
        ->interpolate()
        ->convertBulk([
            new ConversionItem(10000, 'USD', 'EUR', Carbon::parse('2024-01-05')),
            new ConversionItem(10000, 'USD', 'EUR', Carbon::parse('2024-01-08')),
            new ConversionItem(10000, 'USD', 'EUR', Carbon::parse('2024-01-10')),
        ]);

    foreach ($results as $amount) {
        expect($amount)->toBeGreaterThan(9000);
        expect($amount)->toBeLessThan(9500);
    }
});
