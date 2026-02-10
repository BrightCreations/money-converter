<?php

namespace BrightCreations\MoneyConverter\Adapters;

use BrightCreations\MoneyConverter\Contracts\ExchangeRateServiceInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ExchangeRateServiceAdapter implements ExchangeRateServiceInterface
{

    /**
     * @param ExchangeRateServiceInterface $exchangeRateService
     */
    public function __construct(
        private $exchangeRateService
    ) { }

    public function storeExchangeRates(string $currency_code): Collection
    {
        return $this->exchangeRateService->storeExchangeRates($currency_code);
    }

    public function getHistoricalExchangeRate(string $current_currency, string $target_currency, CarbonInterface $date): object
    {
        return $this->exchangeRateService->getHistoricalExchangeRate($current_currency, $target_currency, $date);
    }

    public function isSupportHistoricalExchangeRate(): bool
    {
        return $this->exchangeRateService->isSupportHistoricalExchangeRate();
    }

}
