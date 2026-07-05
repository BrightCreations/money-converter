<?php

use BrightCreations\MoneyConverter\DTOs\ConversionItem;
use BrightCreations\MoneyConverter\Enums\ConversionStrategy;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;

uses(SeedsExchangeRates::class);

beforeEach(function () {
    $this->converter = $this->makeMoneyConverter();
});

it('returns exact db strategy for a historical hit', function () {
    $date = Carbon::parse('2024-01-15')->startOfDay();
    $this->seedHistoricalRate('USD', 'EUR', 0.88, $date);

    $result = $this->converter->convertWithResult(10000, 'USD', 'EUR', $date);

    expect($result->amount)->toBe(8800);
    expect($result->strategy)->toBe(ConversionStrategy::ExactDb);
    expect($result->rate)->toBe('0.88');
});

it('returns interpolate strategy when interpolation is used', function () {
    $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));
    $this->seedHistoricalRate('USD', 'EUR', 0.95, Carbon::parse('2024-01-15'));

    $result = $this->converter
        ->interpolate()
        ->convertWithResult(10000, 'USD', 'EUR', Carbon::parse('2024-01-08'));

    expect($result->strategy)->toBe(ConversionStrategy::Interpolate);
    expect($result->amount)->toBeGreaterThan(9000);
    expect($result->amount)->toBeLessThan(9500);
});

it('returns same currency strategy without hitting the database', function () {
    $result = $this->converter->convertWithResult(10000, 'USD', 'USD');

    expect($result->amount)->toBe(10000);
    expect($result->strategy)->toBe(ConversionStrategy::SameCurrency);
    expect($result->rate)->toBe('1');
});

it('returns bulk results with metadata', function () {
    $this->seedCurrentRate('USD', 'EUR', 0.85);

    $results = $this->converter->convertBulkWithResult([
        new ConversionItem(10000, 'USD', 'EUR'),
        new ConversionItem(10000, 'USD', 'USD'),
    ]);

    expect($results[0]->amount)->toBe(8500);
    expect($results[0]->strategy)->toBe(ConversionStrategy::ExactDb);
    expect($results[1]->strategy)->toBe(ConversionStrategy::SameCurrency);
});
