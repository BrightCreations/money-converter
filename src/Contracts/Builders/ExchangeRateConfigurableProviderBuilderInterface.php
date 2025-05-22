<?php

namespace BrightCreations\MoneyConverter\Contracts\Builders;

use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use BrightCreations\MoneyConverter\Contracts\ExchangeRateProviderBuilderInterface;

interface ExchangeRateConfigurableProviderBuilderInterface extends ExchangeRateProviderBuilderInterface
{
    /**
     * Build the exchange rate provider.
     *
     * @return ConfigurableProvider
     */
    public function build(): ConfigurableProvider;
}
