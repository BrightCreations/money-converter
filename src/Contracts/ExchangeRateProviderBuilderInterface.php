<?php

namespace BrightCreations\MoneyConverter\Contracts;

use Brick\Money\ExchangeRateProvider;

interface ExchangeRateProviderBuilderInterface
{
    /**
     * Build the exchange rate provider.
     */
    public function build(): ExchangeRateProvider;
}
