<?php

namespace Modules\Store\Support;

/**
 * A product's storefront price; `was` is the crossed-out price when it is discounted.
 */
final class Price
{
    public function __construct(
        public readonly float $amount,
        public readonly ?float $was = null,
    ) {}

    public function discounted(): bool
    {
        return $this->was !== null && $this->was > $this->amount;
    }

    /**
     * Whole percent saved when discounted ("20" for 100 → 80), else null.
     */
    public function percentOff(): ?int
    {
        return $this->discounted() && $this->was > 0 ? (int) round(($this->was - $this->amount) / $this->was * 100) : null;
    }

    public function format(string $currency = '₪'): string
    {
        return self::money($this->amount, $currency);
    }

    public function formatWas(string $currency = '₪'): ?string
    {
        return $this->discounted() ? self::money($this->was, $currency) : null;
    }

    /**
     * The price as a page prop: the browser shows these texts and computes nothing from them.
     *
     * @return array{amount: float, label: string, was: ?string, percent_off: ?int, discounted: bool}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'label' => $this->format(),
            'was' => $this->formatWas(),
            'percent_off' => $this->percentOff(),
            'discounted' => $this->discounted(),
        ];
    }

    private static function money(float $value, string $currency): string
    {
        return number_format($value, 2).' '.$currency;
    }
}
