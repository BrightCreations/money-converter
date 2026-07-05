<?php

use BrightCreations\MoneyConverter\DTOs\ConversionItem;
use BrightCreations\MoneyConverter\DTOs\MoneyConverterOptions;
use BrightCreations\MoneyConverter\Enums\ConversionStrategy;
use BrightCreations\MoneyConverter\Events\ConversionPerformed;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

uses(SeedsExchangeRates::class);

beforeEach(function () {
    Event::fake([ConversionPerformed::class]);
    $this->converter = $this->makeMoneyConverter();
});

it('dispatches ConversionPerformed when convertWithResult succeeds', function () {
    $this->seedCurrentRate('USD', 'EUR', 0.85);

    $result = $this->converter->convertWithResult(10000, 'USD', 'EUR');

    expect($result->amount)->toBe(8500);

    Event::assertDispatched(ConversionPerformed::class, function (ConversionPerformed $event) {
        return $event->inputAmount === 10000
            && $event->from === 'USD'
            && $event->to === 'EUR'
            && $event->date === null
            && $event->result->amount === 8500
            && $event->result->strategy === ConversionStrategy::ExactDb
            && $event->result->rate === '0.85';
    });
});

it('dispatches ConversionPerformed for same-currency conversions', function () {
    $this->converter->convertWithResult(10000, 'USD', 'USD');

    Event::assertDispatched(ConversionPerformed::class, function (ConversionPerformed $event) {
        return $event->inputAmount === 10000
            && $event->from === 'USD'
            && $event->to === 'USD'
            && $event->result->strategy === ConversionStrategy::SameCurrency
            && $event->result->rate === '1';
    });
});

it('dispatches one ConversionPerformed per bulk item', function () {
    $this->seedCurrentRate('USD', 'EUR', 0.85);
    $date = Carbon::parse('2024-01-15')->startOfDay();
    $this->seedHistoricalRate('USD', 'GBP', 0.75, $date);

    $this->converter->convertBulkWithResult([
        new ConversionItem(10000, 'USD', 'EUR'),
        new ConversionItem(10000, 'USD', 'GBP', $date),
        new ConversionItem(5000, 'EUR', 'EUR'),
    ]);

    Event::assertDispatched(ConversionPerformed::class, 3);
});

it('does not dispatch when conversion events are disabled in config', function () {
    Config::set('money-converter.events.dispatch_conversion_performed', false);
    $this->seedCurrentRate('USD', 'EUR', 0.85);

    $this->converter = $this->makeMoneyConverter();
    $this->converter->convertWithResult(10000, 'USD', 'EUR');

    Event::assertNotDispatched(ConversionPerformed::class);
});

it('dispatches ConversionPerformed from convertHistorical with the target date', function () {
    $date = Carbon::parse('2024-01-15')->startOfDay();
    $this->seedHistoricalRate('USD', 'EUR', 0.88, $date);

    $this->converter->convertHistorical(
        10000,
        'USD',
        'EUR',
        $date,
        MoneyConverterOptions::defaults()
    );

    Event::assertDispatched(ConversionPerformed::class, function (ConversionPerformed $event) use ($date) {
        return $event->date !== null
            && $event->date->equalTo($date)
            && $event->result->amount === 8800
            && $event->result->strategy === ConversionStrategy::ExactDb;
    });
});
