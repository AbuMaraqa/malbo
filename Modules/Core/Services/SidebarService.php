<?php

namespace Modules\Core\Services;

use Modules\Core\Interfaces\SidebarItemsInterface;

class SidebarService
{
    protected array $items = [];

    public function add(SidebarItemsInterface $item): static
    {
        $this->items[] = $item;
        return $this;
    }

    /**
     * A group another module added, by its title, so a module can add an item to it (call it once
     * every provider has booted: the order modules boot in is not fixed).
     */
    public function group(string $title): ?SidebarGroup
    {
        foreach ($this->items as $item) {
            if ($item instanceof SidebarGroup && $item->title() === $title) {
                return $item;
            }
        }

        return null;
    }

    public function all(): array
    {
        return collect($this->items)
            ->filter(fn($item) => $item->canSee())
            ->map(fn($item) => $item->toArray())
            ->sortBy(fn($item) => $item['sort'] ?? 100)
            ->values()
            ->toArray();
    }

    public function clear(): static
    {
        $this->items = [];
        return $this;
    }
}
