<?php

namespace BrightCreations\MoneyConverter\Enums;

use BrightCreations\MoneyConverter\Contracts\Builders\ExchangeRateConfigurableProviderBuilderInterface;
use BrightCreations\MoneyConverter\Contracts\Builders\ExchangeRatePDOProviderBuilderInterface;

enum ExchangeRateProvidersEnum: string
{
    case PDO = ExchangeRatePDOProviderBuilderInterface::class;
    case CONFIGURABLE = ExchangeRateConfigurableProviderBuilderInterface::class;
}
