<?php

namespace BrightCreations\MoneyConverter\Contracts\Builders;

use Brick\Money\ExchangeRateProvider\PDOProvider;
use BrightCreations\MoneyConverter\Contracts\ExchangeRateProviderBuilderInterface;

interface ExchangeRatePDOProviderBuilderInterface extends ExchangeRateProviderBuilderInterface
{
    /**
     * Build the exchange rate provider.
     *
     * @return PDOProvider
     */
    public function build(): PDOProvider;
}
