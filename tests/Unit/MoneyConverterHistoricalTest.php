<?php

use BrightCreations\ExchangeRates\Contracts\Repositories\CurrencyExchangeRateRepositoryInterface;
use BrightCreations\ExchangeRates\Facades\ExchangeRate;
use BrightCreations\MoneyConverter\Exceptions\MoneyConversionException;
use Carbon\Carbon;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->repository = app(CurrencyExchangeRateRepositoryInterface::class);
    $this->converter = $this->makeMoneyConverter();
});

describe('Historical extrapolation', function () {
    it('extrapolates using nearest previous rate when target is after all stored data', function () {
        $this->repository->updateExchangeRatesHistory('USD', ['EUR' => 0.90], Carbon::parse('2024-01-01'));

        $result = $this->converter
            ->extrapolate()
            ->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-06-01'));

        expect($result)->toBe(9000);
    });

    it('extrapolates using nearest next rate when target is before all stored data', function () {
        $this->repository->updateExchangeRatesHistory('USD', ['EUR' => 0.95], Carbon::parse('2024-06-01'));

        $result = $this->converter
            ->extrapolate()
            ->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-01-01'));

        expect($result)->toBe(9500);
    });

    it('throws when extrapolate is disabled and target is outside stored range', function () {
        $this->repository->updateExchangeRatesHistory('USD', ['EUR' => 0.90], Carbon::parse('2024-01-01'));

        $this->converter->convertHistorical(10000, 'USD', 'EUR', Carbon::parse('2024-06-01'));
    })->throws(MoneyConversionException::class);

    it('throws when extrapolate is enabled but target is between two bounds without interpolate', function () {
        $this->repository->updateExchangeRatesHistory('USD', ['EUR' => 0.90], Carbon::parse('2024-01-01'));
        $this->repository->updateExchangeRatesHistory('USD', ['EUR' => 0.95], Carbon::parse('2024-01-15'));

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
                $this->repository->updateExchangeRates('USD', ['EUR' => 0.85]);

                return new Collection;
            });

        $result = $this->makeMoneyConverter()->convertFresh(10000, 'USD', 'EUR');

        expect($result)->toBe(8500);
    });
});
