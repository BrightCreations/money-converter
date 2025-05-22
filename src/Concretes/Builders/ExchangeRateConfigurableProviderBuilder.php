<?php

namespace BrightCreations\MoneyConverter\Concretes\Builders;

use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use BrightCreations\MoneyConverter\Contracts\Builders\ExchangeRateConfigurableProviderBuilderInterface;

class ExchangeRateConfigurableProviderBuilder implements ExchangeRateConfigurableProviderBuilderInterface
{
    /**
     * Build the exchange rate provider.
     *
     * @return ConfigurableProvider
     */
    public function build(): ConfigurableProvider
    {
        $provider = new ConfigurableProvider();

        $provider->setExchangeRate('EUR', 'USD', '1.0987');
        $provider->setExchangeRate('USD', 'EUR', '0.9123');
        $provider->setExchangeRate('AED', 'USD', '0.27');
        $provider->setExchangeRate('USD', 'AED', '3.65');
        $provider->setExchangeRate('AED', 'GBP', '4.65');       $provider->setExchangeRate('AED', 'EGP', '4.65');
        $provider->setExchangeRate('GBP', 'AED', '0.215');      $provider->setExchangeRate('EGP', 'AED', '0.215');
        $provider->setExchangeRate('BHD', 'GBP', '2.1');        $provider->setExchangeRate('BHD', 'EGP', '2.1');
        $provider->setExchangeRate('GBP', 'BHD', '0.476');      $provider->setExchangeRate('EGP', 'BHD', '0.476');
        $provider->setExchangeRate('GBP', 'EUR', '1.16');       $provider->setExchangeRate('EGP', 'EUR', '1.16');
        $provider->setExchangeRate('EUR', 'GBP', '0.86');       $provider->setExchangeRate('EUR', 'EGP', '0.86');
        $provider->setExchangeRate('CAD', 'GBP', '0.56');       $provider->setExchangeRate('CAD', 'EGP', '0.56');
        $provider->setExchangeRate('GBP', 'CAD', '1.77');       $provider->setExchangeRate('EGP', 'CAD', '1.77');
        $provider->setExchangeRate('CHF', 'GBP', '0.88');       $provider->setExchangeRate('CHF', 'EGP', '0.88');
        $provider->setExchangeRate('GBP', 'CHF', '1.13');       $provider->setExchangeRate('EGP', 'CHF', '1.13');
        $provider->setExchangeRate('USD', 'GBP', '0.78');       $provider->setExchangeRate('USD', 'EGP', '0.78');
        $provider->setExchangeRate('GBP', 'USD', '1.28');       $provider->setExchangeRate('EGP', 'USD', '1.28');

        return $provider;
    }
}
