<?php

namespace BrightCreations\MoneyConverter\Concretes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use BrightCreations\MoneyConverter\DTOs\MoneyConversionResult;
use BrightCreations\MoneyConverter\Enums\ConversionStrategy;

final class ResolvedConversion
{
    public function __construct(
        public readonly int $amount,
        public readonly string $rate,
        public readonly ConversionStrategy $strategy,
        public readonly ?string $provider = null,
    ) {}

    public static function sameCurrency(int $amount): self
    {
        return new self($amount, '1', ConversionStrategy::SameCurrency);
    }

    public function toResult(): MoneyConversionResult
    {
        return new MoneyConversionResult($this->amount, $this->rate, $this->strategy, $this->provider);
    }

    public static function fromRate(
        Money $money,
        BigDecimal|string $rate,
        ConversionStrategy $strategy,
        ?string $provider,
        RoundingMode $roundingMode,
    ): self {
        $normalizedRate = $rate instanceof BigDecimal
            ? $rate
            : BigDecimal::of((string) $rate);

        $amount = $money->multipliedBy($normalizedRate, $roundingMode)
            ->getMinorAmount()
            ->toInt();

        return new self($amount, (string) $normalizedRate, $strategy, $provider);
    }
}
