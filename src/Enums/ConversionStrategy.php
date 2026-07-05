<?php

namespace BrightCreations\MoneyConverter\Enums;

enum ConversionStrategy: string
{
    case SameCurrency = 'same_currency';
    case ExactDb = 'exact_db';
    case ApiFetch = 'api_fetch';
    case Proxy = 'proxy';
    case Interpolate = 'interpolate';
    case Extrapolate = 'extrapolate';
    case Fresh = 'fresh';
}
