<?php

namespace App\Providers;

use App\Services\PaymentGatewayRegistry;
use App\Services\PluginManager;
use App\Services\PluginNavRegistry;
use App\Services\UpdateCheckService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginNavRegistry::class, fn() => new PluginNavRegistry());
        $this->app->singleton(PaymentGatewayRegistry::class, fn() => new PaymentGatewayRegistry());
        $this->app->singleton(PluginManager::class, fn() => new PluginManager());
        $this->app->singleton(UpdateCheckService::class, fn() => new UpdateCheckService());
    }

    public function boot(): void
    {
        Paginator::useBootstrap();
        Model::preventLazyLoading(true);

        $this->app->make(PluginManager::class)->bootEnabled();
    }
}
