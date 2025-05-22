<?php

namespace BrightCreations\MoneyConverter\Contracts;

use Carbon\CarbonInterface;

interface ExchangeRateServiceInterface
{
    /**
     * Get the exchange rate for a given currency code.
     * 
     * @param string $currency_code
     */
    public function storeExchangeRates($currency_code);

    /**
     * Get the exchange rate for a given currency code.
     * 
     * @param string $current_currency
     * @param string $target_currency
     * @param CarbonInterface $date
     */
    public function getHistoricalExchangeRate($current_currency, $target_currency, $date);

    /**
     * Check if the service supports historical exchange rates.
     * 
     * @return bool
     */
    public function isSupportHistoricalExchangeRate(): bool;
}
