<?php

namespace BrightCreations\MoneyConverter\Contracts;

use Brick\Money\ExchangeRateProvider;

interface ExchangeRateProviderBuilderInterface
{
    /**
     * Build the exchange rate provider.
     *
     * @return ExchangeRateProvider
     */
    public function build(): ExchangeRateProvider;
}
