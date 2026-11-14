<?php

namespace Modules\Items\Services\Couriers;

use InvalidArgumentException;

/**
 * The couriers listed in config/couriers.php: their names for the admin and their gateways.
 */
class CourierRegistry
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_map(fn (array $courier) => ['value' => $courier['key'], 'label' => __($courier['name'])], $this->all());
    }

    public function has(string $key): bool
    {
        return $this->find($key) !== null;
    }

    /**
     * The courier's name in the current language; a key no longer in the list shows as itself.
     */
    public function name(?string $key): string
    {
        $courier = $key !== null ? $this->find($key) : null;

        return $courier ? __($courier['name']) : (string) $key;
    }

    /**
     * @throws InvalidArgumentException when the courier is not listed or has no gateway
     */
    public function gateway(string $key): CourierGateway
    {
        $class = $this->find($key)['gateway'] ?? null;

        if (! is_string($class) || $class === '') {
            throw new InvalidArgumentException("No gateway for the courier [{$key}].");
        }

        $gateway = app($class);

        if (! $gateway instanceof CourierGateway) {
            throw new InvalidArgumentException("The gateway of the courier [{$key}] is not a CourierGateway.");
        }

        return $gateway;
    }

    /**
     * @return array{key: string, name: string, gateway: ?string}|null
     */
    private function find(string $key): ?array
    {
        foreach ($this->all() as $courier) {
            if ($courier['key'] === $key) {
                return $courier;
            }
        }

        return null;
    }

    /**
     * @return list<array{key: string, name: string, gateway: ?string}>
     */
    private function all(): array
    {
        return array_values(array_filter((array) config('couriers.couriers', []), fn ($courier) => is_array($courier) && isset($courier['key'], $courier['name'])));
    }
}
