<?php

use Brick\Money\CurrencyConverter;
use Brick\Money\Money;
use BrightCreations\MoneyConverter\Concretes\Builders\ExchangeRateConfigurableProviderBuilder;
use BrightCreations\MoneyConverter\Enums\ExchangeRateProvidersEnum;
use BrightCreations\MoneyConverter\MoneyConverterServiceProvider;

it('loads exchange rates from config into the configurable provider', function () {
    $this->app->register(MoneyConverterServiceProvider::class);

    $this->app['config']->set('money-converter.default_provider', ExchangeRateProvidersEnum::CONFIGURABLE->value);

    $provider = app(ExchangeRateConfigurableProviderBuilder::class)->build();
    $converter = new CurrencyConverter($provider);

    $eurCents = $converter->convert(
        Money::ofMinor(10000, 'USD'),
        'EUR'
    )->getMinorAmount()->toInt();

    expect($eurCents)->toBe(9123);
});
