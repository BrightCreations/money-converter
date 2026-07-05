<?php

namespace BrightCreations\MoneyConverter\DTOs;

use BrightCreations\MoneyConverter\Enums\ConversionStrategy;

final class MoneyConversionResult
{
    public function __construct(
        public readonly int $amount,
        public readonly string $rate,
        public readonly ConversionStrategy $strategy,
        public readonly ?string $provider = null,
    ) {}
}
