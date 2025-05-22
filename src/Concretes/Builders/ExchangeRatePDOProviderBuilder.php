<?php

namespace BrightCreations\MoneyConverter\Concretes\Builders;

use Brick\Money\ExchangeRateProvider\PDOProvider;
use Brick\Money\ExchangeRateProvider\PDOProviderConfiguration;
use BrightCreations\MoneyConverter\Contracts\Builders\ExchangeRatePDOProviderBuilderInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ExchangeRatePDOProviderBuilder implements ExchangeRatePDOProviderBuilderInterface
{
    /**
     * Build the exchange rate provider.
     *
     * @return PDOProvider
     */
    public function build(): PDOProvider
    {
        $config = new PDOProviderConfiguration(
            tableName:                  Config::get('money-converter.exchange_rates.pdo.table'),
            exchangeRateColumnName:     Config::get('money-converter.exchange_rates.pdo.column'),
            sourceCurrencyColumnName:   Config::get('money-converter.exchange_rates.pdo.source_currency_column'),
            targetCurrencyColumnName:   Config::get('money-converter.exchange_rates.pdo.target_currency_column'),
        );

        return new PDOProvider(DB::connection()->getPdo(), $config);
    }
}
