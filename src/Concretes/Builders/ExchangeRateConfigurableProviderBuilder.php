<?php

namespace BrightCreations\MoneyConverter\Concretes\Builders;

use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use BrightCreations\MoneyConverter\Contracts\Builders\ExchangeRateConfigurableProviderBuilderInterface;
use Illuminate\Support\Facades\Config;

class ExchangeRateConfigurableProviderBuilder implements ExchangeRateConfigurableProviderBuilderInterface
{
    /**
     * Build the exchange rate provider.
     */
    public function build(): ConfigurableProvider
    {
        $provider = new ConfigurableProvider;

        foreach (Config::get('money-converter.exchange_rates.configurable.rates', []) as $base => $targets) {
            foreach ($targets as $target => $rate) {
                $provider->setExchangeRate($base, $target, (string) $rate);
            }
        }

        return $provider;
    }
}
