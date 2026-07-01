<?php

namespace App\Services;

class PaymentGatewayRegistry
{
    protected array $gateways = [];

    public function register(array $gateway): void
    {
        $this->gateways[$gateway['slug']] = $gateway;
    }

    public function all(): array
    {
        return $this->gateways;
    }

    public function get(string $slug): ?array
    {
        return $this->gateways[$slug] ?? null;
    }

    public function getControllerMap(): array
    {
        $map = [];
        foreach ($this->gateways as $slug => $gateway) {
            $map[$slug] = $gateway['controller_class'];
        }
        return $map;
    }

    public function getCallbackMap(): array
    {
        $map = [];
        foreach ($this->gateways as $slug => $gateway) {
            if (!empty($gateway['has_callback'])) {
                $map[$slug] = $gateway['controller_class'];
            }
        }
        return $map;
    }
}
