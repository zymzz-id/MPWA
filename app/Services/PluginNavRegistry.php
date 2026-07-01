<?php

namespace App\Services;

class PluginNavRegistry
{
    protected array $items = [];

    public function add(array $item): void
    {
        $this->items[] = $item;
    }

    public function all(): array
    {
        return $this->items;
    }
}
