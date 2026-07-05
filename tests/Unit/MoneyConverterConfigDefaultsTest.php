<?php

use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

uses(SeedsExchangeRates::class);

it('interpolates using config default_options without a fluent chain', function () {
    Config::set('money-converter.default_options', [
        'extrapolate' => false,
        'interpolate' => true,
        'needs_fresh' => false,
        'on_fail' => 'throw',
    ]);

    $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));
    $this->seedHistoricalRate('USD', 'EUR', 0.95, Carbon::parse('2024-01-15'));

    $converter = $this->makeMoneyConverter();

    $result = $converter->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-01-08'));

    expect($result)->toBeGreaterThan(9000);
    expect($result)->toBeLessThan(9500);
});

it('maps on_fail fetch from config to fetchOnFail behaviour', function () {
    Config::set('money-converter.default_options', [
        'extrapolate' => false,
        'interpolate' => false,
        'needs_fresh' => false,
        'on_fail' => 'fetch',
    ]);

    $converter = $this->makeMoneyConverter();

    expect($converter->isFetchOnFail())->toBeTrue();
});
