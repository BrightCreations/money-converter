<?php

namespace BrightCreations\MoneyConverter\DTOs;

use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;

final class MoneyConverterOptions
{
    public function __construct(
        public readonly bool $extrapolate = false,
        public readonly bool $interpolate = false,
        public readonly bool $needsFresh = false,
        public readonly int $onFail = MoneyConverterInterface::ON_FAIL_THROW_EXCEPTION,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    public function withExtrapolate(bool $extrapolate = true): self
    {
        return new self(
            extrapolate: $extrapolate,
            interpolate: $this->interpolate,
            needsFresh: $this->needsFresh,
            onFail: $this->onFail,
        );
    }

    public function withInterpolate(bool $interpolate = true): self
    {
        return new self(
            extrapolate: $this->extrapolate,
            interpolate: $interpolate,
            needsFresh: $this->needsFresh,
            onFail: $this->onFail,
        );
    }

    public function withNeedsFresh(bool $needsFresh = true): self
    {
        return new self(
            extrapolate: $this->extrapolate,
            interpolate: $this->interpolate,
            needsFresh: $needsFresh,
            onFail: $this->onFail,
        );
    }

    public function withThrowOnFail(): self
    {
        return $this->withOnFail(MoneyConverterInterface::ON_FAIL_THROW_EXCEPTION);
    }

    public function withFetchOnFail(): self
    {
        return $this->withOnFail(MoneyConverterInterface::ON_FAIL_FETCH_EXCHANGE_RATES);
    }

    public function withOnFail(int $onFail): self
    {
        if ($onFail !== MoneyConverterInterface::ON_FAIL_THROW_EXCEPTION
            && $onFail !== MoneyConverterInterface::ON_FAIL_FETCH_EXCHANGE_RATES) {
            throw new \InvalidArgumentException(
                '[MoneyConverter] Invalid on fail value: '.$onFail.'. Expected: '
                .MoneyConverterInterface::ON_FAIL_FETCH_EXCHANGE_RATES.' for fetching exchange rates'
                .' or '.MoneyConverterInterface::ON_FAIL_THROW_EXCEPTION.' for throwing an exception. Got: '.$onFail
            );
        }

        return new self(
            extrapolate: $this->extrapolate,
            interpolate: $this->interpolate,
            needsFresh: $this->needsFresh,
            onFail: $onFail,
        );
    }
}
