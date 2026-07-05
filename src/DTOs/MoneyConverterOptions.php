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

    public static function fromConfig(?array $config = null): self
    {
        if ($config === null || $config === []) {
            return self::defaults();
        }

        $options = self::defaults();

        if (array_key_exists('extrapolate', $config)) {
            $options = $options->withExtrapolate((bool) $config['extrapolate']);
        }

        if (array_key_exists('interpolate', $config)) {
            $options = $options->withInterpolate((bool) $config['interpolate']);
        }

        if (array_key_exists('needs_fresh', $config)) {
            $options = $options->withNeedsFresh((bool) $config['needs_fresh']);
        }

        if (array_key_exists('on_fail', $config)) {
            $options = $options->withOnFail(self::mapOnFailFromConfig($config['on_fail']));
        }

        return $options;
    }

    private static function mapOnFailFromConfig(mixed $onFail): int
    {
        return match ($onFail) {
            'throw' => MoneyConverterInterface::ON_FAIL_THROW_EXCEPTION,
            'fetch' => MoneyConverterInterface::ON_FAIL_FETCH_EXCHANGE_RATES,
            default => throw new \InvalidArgumentException(
                '[MoneyConverter] Invalid default_options.on_fail value: '.(string) $onFail.'. Expected: "throw" or "fetch".'
            ),
        };
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
