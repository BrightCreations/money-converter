<?php

namespace BrightCreations\MoneyConverter\Adapters;

use BrightCreations\MoneyConverter\Contracts\ExchangeRateServiceInterface;

class ExchangeRateServiceAdapter implements ExchangeRateServiceInterface
{

    /**
     * @param ExchangeRateServiceInterface $exchangeRateService
     */
    public function __construct(
        private $exchangeRateService
    ) { }

    public function storeExchangeRates($currency_code)
    {
        return $this->exchangeRateService->storeExchangeRates($currency_code);
    }

    public function getHistoricalExchangeRate($current_currency, $target_currency, $date)
    {
        return $this->exchangeRateService->getHistoricalExchangeRate($current_currency, $target_currency, $date);
    }

    public function isSupportHistoricalExchangeRate(): bool
    {
        return $this->exchangeRateService->isSupportHistoricalExchangeRate();
    }

}
