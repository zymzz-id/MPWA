<?php

namespace App\Plugins;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

abstract class BasePluginServiceProvider extends ServiceProvider
{
    protected string $pluginSlug = '';

    public function boot(): void
    {
    }

    public function register(): void
    {
    }

    public static function info(): array
    {
        $class = static::class;
        $reflection = new \ReflectionClass($class);
        $pluginDir = dirname($reflection->getFileName(), 2);
        $infoPath = $pluginDir . '/info.json';

        if (!file_exists($infoPath)) {
            return [];
        }

        return json_decode(file_get_contents($infoPath), true) ?? [];
    }

    public function getNavItems(): array
    {
        return [];
    }

    protected function loadPluginRoutes(): void
    {
        $routesFile = base_path("plugins/{$this->pluginSlug}/routes.php");
        if (file_exists($routesFile)) {
            if ($this->app->routesAreCached()) {
                return;
            }
            $this->callAfterResolving('router', function () use ($routesFile) {
                Route::middleware('web')->group($routesFile);
            });
        }
    }

    protected function loadPluginViews(): void
    {
        $viewsPath = base_path("plugins/{$this->pluginSlug}/resources/views");
        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->pluginSlug);
        }
    }

    protected function loadPluginMigrations(): void
    {
        $migrationsPath = base_path("plugins/{$this->pluginSlug}/database/migrations");
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    protected function loadPluginTranslations(): void
    {
        $langPath = base_path("plugins/{$this->pluginSlug}/lang");
        if (is_dir($langPath)) {
            $this->loadJsonTranslationsFrom($langPath);
            $this->loadTranslationsFrom($langPath, $this->pluginSlug);
        }
    }

    protected function loadPluginCommands(): void
    {
        $commandsPath = base_path("plugins/{$this->pluginSlug}/src/Console/Commands");
        if (!is_dir($commandsPath)) {
            return;
        }

        $studlySlug = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $this->pluginSlug)));
        $namespace = "Plugins\\{$studlySlug}\\Console\\Commands";

        $finder = new Finder();
        $finder->files()->in($commandsPath)->name('*.php');

        $commands = [];
        foreach ($finder as $file) {
            $className = $namespace . '\\' . $file->getBasename('.php');
            if (class_exists($className)) {
                $commands[] = $className;
            }
        }

        if ($commands) {
            $this->commands($commands);
        }
    }

    protected function loadPluginProviders(): void
    {
        $providersPath = base_path("plugins/{$this->pluginSlug}/src/Providers");
        if (!is_dir($providersPath)) {
            return;
        }

        $studlySlug = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $this->pluginSlug)));
        $namespace = "Plugins\\{$studlySlug}\\Providers";

        $finder = new Finder();
        $finder->files()->in($providersPath)->name('*.php');

        foreach ($finder as $file) {
            $className = $namespace . '\\' . $file->getBasename('.php');
            if (class_exists($className)) {
                $this->app->register($className);
            }
        }
    }

    protected function pluginPath(string $path = ''): string
    {
        return base_path("plugins/{$this->pluginSlug}" . ($path ? '/' . $path : ''));
    }
}
