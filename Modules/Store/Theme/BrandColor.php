<?php

namespace Modules\Store\Theme;

use InvalidArgumentException;

/**
 * A store's brand color: validation, WCAG contrast, the text color that reads on it, and a
 * 50–900 shade scale for the storefront theme.
 */
final class BrandColor
{
    public const DARK_TEXT = '#111827';

    private function __construct(private string $hex) {}

    public static function isValid(string $hex): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    public static function fromHex(string $hex): self
    {
        if (! self::isValid($hex)) {
            throw new InvalidArgumentException("Not a #rrggbb color: {$hex}");
        }

        return new self(strtoupper($hex));
    }

    public function hex(): string
    {
        return $this->hex;
    }

    public function contrastRatio(string $other = '#FFFFFF'): float
    {
        [$a, $b] = [self::luminance($this->hex), self::luminance(self::fromHex($other)->hex)];

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public function onBrand(): string
    {
        return $this->contrastRatio('#FFFFFF') >= 4.5 ? '#FFFFFF' : self::DARK_TEXT;
    }

    /**
     * @return array<int, string> 50 (lightest) … 900 (darkest), same hue and saturation
     */
    public function shades(): array
    {
        [$h, $s, $l] = self::toHsl($this->hex);
        $darker = fn (float $step) => min($l, max(4, $l - ($l - 4) * $step)); // never lighter than the color

        $lightness = [
            50 => max($l, 96), 100 => max($l, 91), 200 => max($l, 82), 300 => max($l, 70), 400 => max($l, ($l + 58) / 2),
            600 => $darker(0.2), 700 => $darker(0.4), 800 => $darker(0.6), 900 => $darker(0.8),
        ];

        $shades = [];
        foreach ([50, 100, 200, 300, 400] as $key) {
            $shades[$key] = self::fromHsl($h, $s, $lightness[$key]);
        }
        $shades[500] = $this->hex;
        foreach ([600, 700, 800, 900] as $key) {
            $shades[$key] = self::fromHsl($h, $s, $lightness[$key]);
        }

        return $shades;
    }

    private static function luminance(string $hex): float
    {
        $channel = function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(substr($hex, 1, 2)) + 0.7152 * $channel(substr($hex, 3, 2)) + 0.0722 * $channel(substr($hex, 5, 2));
    }

    /**
     * @return array{0: float, 1: float, 2: float} hue 0–360, saturation and lightness 0–100
     */
    private static function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d == 0) {
            return [0.0, 0.0, $l * 100];
        }

        $s = $d / (1 - abs(2 * $l - 1));
        $h = match ($max) {
            $r => 60 * fmod((($g - $b) / $d), 6),
            $g => 60 * ((($b - $r) / $d) + 2),
            default => 60 * ((($r - $g) / $d) + 4),
        };

        return [$h < 0 ? $h + 360 : $h, $s * 100, $l * 100];
    }

    private static function fromHsl(float $h, float $s, float $l): string
    {
        $s /= 100;
        $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf('#%02X%02X%02X', round(($r + $m) * 255), round(($g + $m) * 255), round(($b + $m) * 255));
    }
}
