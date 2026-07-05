<?php

use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;

uses(SeedsExchangeRates::class);

beforeEach(function () {
    $this->converter = $this->makeMoneyConverter();
});

describe('Same currency short-circuit', function () {
    it('returns input unchanged from convert', function () {
        expect($this->converter->convert(10000, 'USD', 'USD'))->toBe(10000);
    });

    it('returns input unchanged from convertCurrent', function () {
        expect($this->converter->convertCurrent(10000, 'USD', 'USD'))->toBe(10000);
    });

    it('returns input unchanged from convertFresh', function () {
        expect($this->converter->convertFresh(10000, 'USD', 'USD'))->toBe(10000);
    });

    it('returns input unchanged from convertHistorical', function () {
        expect($this->converter->convertHistorical(10000, 'USD', 'USD', now()))->toBe(10000);
    });

    it('returns input unchanged from convertToday', function () {
        expect($this->converter->convertToday(10000, 'USD', 'USD'))->toBe(10000);
    });
});

it('converts using current rates from the database', function () {
    $this->seedCurrentRate('USD', 'EUR', 0.85);

    expect($this->converter->convertCurrent(10000, 'USD', 'EUR'))->toBe(8500);
});

it('throws InvalidArgumentException for an invalid on_fail value', function () {
    $this->converter->convert(10000, 'USD', 'EUR', null, 99);
})->throws(InvalidArgumentException::class);

it('applies on_fail only for a single convert call', function () {
    $singleton = app(MoneyConverterInterface::class);

    try {
        $singleton->convert(10000, 'USD', 'EUR', null, MoneyConverterInterface::ON_FAIL_FETCH_EXCHANGE_RATES);
    } catch (Throwable) {
    }

    expect($singleton->isThrowOnFail())->toBeTrue();
});
