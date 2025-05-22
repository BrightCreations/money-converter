<?php

namespace BrightCreations\MoneyConverter;

use Brick\Money\CurrencyConverter;
use Brick\Money\ExchangeRateProvider;
use BrightCreations\MoneyConverter\Adapters\ExchangeRateServiceAdapter;
use BrightCreations\MoneyConverter\Concretes\Builders\ExchangeRateConfigurableProviderBuilder;
use BrightCreations\MoneyConverter\Concretes\Builders\ExchangeRatePDOProviderBuilder;
use BrightCreations\MoneyConverter\Concretes\MoneyConverter;
use BrightCreations\MoneyConverter\Contracts\Builders\ExchangeRateConfigurableProviderBuilderInterface;
use BrightCreations\MoneyConverter\Contracts\Builders\ExchangeRatePDOProviderBuilderInterface;
use BrightCreations\MoneyConverter\Contracts\ExchangeRateProviderBuilderInterface;
use BrightCreations\MoneyConverter\Contracts\ExchangeRateServiceInterface;
use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class MoneyConverterServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../config/money-converter.php' => $this->app->configPath('money-converter.php'),
        ], 'money-converter-config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/money-converter.php',
            'money-converter'
        );

        // Register classes in the container
        $this->registerExchangeRateProviderBuilders();
        $this->registerExchangeRateProvider();
        $this->registerDependencyCurrencyConverter();
        $this->registerServices();
    }

    public function registerExchangeRateProviderBuilders(): void
    {
        // Register the PDO provider builder
        $this->app->singleton(ExchangeRatePDOProviderBuilderInterface::class, ExchangeRatePDOProviderBuilder::class);
        // Register the Configurable provider builder
        $this->app->singleton(ExchangeRateConfigurableProviderBuilderInterface::class, ExchangeRateConfigurableProviderBuilder::class);
        // Register the default provider builder
        $this->app->singleton(ExchangeRateProviderBuilderInterface::class, function () {
            return $this->app->make(Config::get('money-converter.default_provider'));
        });
    }

    public function registerExchangeRateProvider(): void
    {
        // Register the exchange rate provider
        // This will be the default provider
        $this->app->singleton(ExchangeRateProvider::class, function () {
            return $this->app->make(ExchangeRateProviderBuilderInterface::class)->build();
        });
    }

    public function registerDependencyCurrencyConverter(): void
    {
        // Register the CurrencyConverter class
        // This is the class that will be used to convert currencies
        $this->app->singleton(CurrencyConverter::class, function () {
            return new CurrencyConverter($this->app->make(ExchangeRateProvider::class));
        });
    }

    public function registerServices(): void
    {
        // Register ExchangeRateServiceInterface
        // This service will be used to fetch exchange rates when they are not found in the database
        $this->app->singleton(ExchangeRateServiceInterface::class, function () {
            return new ExchangeRateServiceAdapter($this->app->make(Config::get('money-converter.exchange_rates_service')));
        });
        // Register the MoneyConverter class
        // This is the class that will be used to convert currencies from outside the package
        // This class will use the CurrencyConverter class to convert currencies
        $this->app->singleton(MoneyConverterInterface::class, MoneyConverter::class);
    }
}
