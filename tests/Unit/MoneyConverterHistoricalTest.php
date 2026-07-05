<?php

use BrightCreations\ExchangeRates\Facades\ExchangeRate;
use BrightCreations\MoneyConverter\Exceptions\MoneyConversionException;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;
use Illuminate\Support\Collection;

uses(SeedsExchangeRates::class);

beforeEach(function () {
    $this->repository = $this->repository();
    $this->converter = $this->makeMoneyConverter();
});

describe('Historical conversion strategies', function () {
    it('converts using an exact historical date match', function () {
        $date = Carbon::parse('2024-01-15')->startOfDay();
        $this->seedHistoricalRate('USD', 'EUR', 0.88, $date);

        $result = $this->converter->convertHistorical(10000, 'USD', 'EUR', $date);

        expect($result)->toBe(8800);
    });

    it('interpolates between two bounding historical rates', function () {
        $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));
        $this->seedHistoricalRate('USD', 'EUR', 0.95, Carbon::parse('2024-01-15'));

        $result = $this->converter
            ->interpolate()
            ->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-01-08'));

        expect($result)->toBeGreaterThan(9000);
        expect($result)->toBeLessThan(9500);
    });

    it('derives a cross-rate via proxy currency when direct pair is missing', function () {
        $date = Carbon::parse('2024-03-01')->startOfDay();
        $this->seedProxyRatesForDate($date);

        $result = $this->converter->convertHistorical(10000, 'GBP', 'EUR', $date);

        expect($result)->toBeGreaterThan(0);
    });
});

describe('Historical extrapolation', function () {
    it('extrapolates using nearest previous rate when target is after all stored data', function () {
        $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));

        $result = $this->converter
            ->extrapolate()
            ->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-06-01'));

        expect($result)->toBe(9000);
    });

    it('extrapolates using nearest next rate when target is before all stored data', function () {
        $this->seedHistoricalRate('USD', 'EUR', 0.95, Carbon::parse('2024-06-01'));

        $result = $this->converter
            ->extrapolate()
            ->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-01-01'));

        expect($result)->toBe(9500);
    });

    it('throws when extrapolate is disabled and target is outside stored range', function () {
        $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));

        $this->converter->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-06-01'));
    })->throws(MoneyConversionException::class);

    it('throws when extrapolate is enabled but target is between two bounds without interpolate', function () {
        $this->seedHistoricalRate('USD', 'EUR', 0.90, Carbon::parse('2024-01-01'));
        $this->seedHistoricalRate('USD', 'EUR', 0.95, Carbon::parse('2024-01-15'));

        $this->converter
            ->extrapolate()
            ->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-01-08'));
    })->throws(MoneyConversionException::class);
});

describe('Fresh conversion', function () {
    it('uses bulk store when converting fresh', function () {
        ExchangeRate::shouldReceive('storeBulkExchangeRatesForMultipleCurrencies')
            ->once()
            ->with(['USD', 'EUR'])
            ->andReturnUsing(function () {
                $this->seedCurrentRate('USD', 'EUR', 0.85);

                return new Collection;
            });

        $result = $this->makeMoneyConverter()->convertFresh(10000, 'USD', 'EUR');

        expect($result)->toBe(8500);
    });
});
