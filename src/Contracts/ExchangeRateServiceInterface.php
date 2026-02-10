<?php

namespace BrightCreations\MoneyConverter\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface ExchangeRateServiceInterface
{
    /**
     * Get the exchange rate for a given currency code.
     * 
     * @param string $currency_code
     * @return Collection<{exchange_rate: float}>
     */
    public function storeExchangeRates(string $currency_code): Collection;

    /**
     * Get the exchange rate for a given currency code.
     * 
     * @param string $current_currency
     * @param string $target_currency
     * @param CarbonInterface $date
     * @return object{exchange_rate: float}
     */
    public function getHistoricalExchangeRate(string $current_currency, string $target_currency, CarbonInterface $date): object;

    /**
     * Check if the service supports historical exchange rates.
     * 
     * @return bool
     */
    public function isSupportHistoricalExchangeRate(): bool;
}
