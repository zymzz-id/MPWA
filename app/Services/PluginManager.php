<?php

namespace App\Services;

use App\Models\Plugin;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class PluginManager
{
    protected array $booted = [];

    public function bootEnabled(): void
	{
		if (app()->runningInConsole()) {
			return;
		}

		try {
			if (!Schema::hasTable('plugins')) {
				return;
			}
		} catch (\Throwable $e) {
			return;
		}

		$enabledPlugins = Plugin::where('is_enabled', true)->pluck('slug')->toArray();
		$sorted = $this->sortByDependencies($enabledPlugins);

		foreach ($sorted as $slug) {
			$this->bootPlugin($slug);
		}
	}

    protected function sortByDependencies(array $slugs): array
    {
        $infoMap = [];
        foreach ($slugs as $slug) {
            $infoPath = base_path("plugins/{$slug}/info.json");
            $info = file_exists($infoPath) ? (json_decode(file_get_contents($infoPath), true) ?? []) : [];
            $infoMap[$slug] = $info['requires'] ?? [];
        }

        $sorted = [];
        $visited = [];

        $visit = function (string $slug) use (&$visit, &$sorted, &$visited, $infoMap, $slugs) {
            if (isset($visited[$slug])) {
                return;
            }
            $visited[$slug] = true;
            foreach ($infoMap[$slug] ?? [] as $dep) {
                if (in_array($dep, $slugs)) {
                    $visit($dep);
                }
            }
            $sorted[] = $slug;
        };

        foreach ($slugs as $slug) {
            $visit($slug);
        }

        return $sorted;
    }

    protected function bootPlugin(string $slug): void
    {
        if (in_array($slug, $this->booted)) {
            return;
        }

        $providerPath = base_path("plugins/{$slug}/PluginServiceProvider.php");

        if (!file_exists($providerPath)) {
            return;
        }

        $infoPath = base_path("plugins/{$slug}/info.json");
        if (!file_exists($infoPath)) {
            return;
        }

        $info = json_decode(file_get_contents($infoPath), true);
        if (empty($info['entry'])) {
            return;
        }

        $studlySlug = $this->studlySlug($slug);
        $namespace = "Plugins\\{$studlySlug}\\{$info['entry']}";

        $srcPath = base_path("plugins/{$slug}/src");
        if (is_dir($srcPath)) {
            $loader = require base_path('vendor/autoload.php');
            $loader->addPsr4("Plugins\\{$studlySlug}\\", $srcPath . DIRECTORY_SEPARATOR);
        }

        if (!class_exists($namespace)) {
            require_once $providerPath;
        }

        if (!class_exists($namespace)) {
            return;
        }

        $provider = new $namespace(app());
        app()->register($provider);

        $navRegistry = app(PluginNavRegistry::class);
        foreach ($provider->getNavItems() as $item) {
            $navRegistry->add($item);
        }

        $this->booted[] = $slug;
    }

    public function all(): array
	{
		if (app()->runningInConsole()) {
			return [];
		}

		try {
			if (!Schema::hasTable('plugins')) {
				return [];
			}
		} catch (\Throwable $e) {
			return [];
		}

		$records = Plugin::all()->keyBy('slug');
        $plugins = [];

        $pluginsDir = base_path('plugins');
        if (!is_dir($pluginsDir)) {
            return [];
        }

        $installedSlugs = $records->filter(fn($r) => $r->is_enabled)->keys()->toArray();

        foreach (File::directories($pluginsDir) as $dir) {
            $slug = basename($dir);
            $infoPath = $dir . '/info.json';

            if (!file_exists($infoPath)) {
                continue;
            }

            $info = json_decode(file_get_contents($infoPath), true);
            if (!$info) {
                continue;
            }

            $record = $records->get($slug);

            $screenshotUrl = null;
            foreach (['screenshot.jpg', 'screenshot.png', 'screenshot.webp'] as $ext) {
                $screenshotPath = public_path('plugins/' . $slug . '/' . $ext);
                if (file_exists($screenshotPath)) {
                    $screenshotUrl = url('plugins/' . $slug . '/' . $ext);
                    break;
                }
            }

            $requires = $info['requires'] ?? [];
            $missingDependencies = [];
            foreach ($requires as $dep) {
                $depRecord = $records->get($dep);
                if (!$depRecord || !$depRecord->is_enabled) {
                    $missingDependencies[] = $dep;
                }
            }

            $plugins[] = array_merge($info, [
                'slug' => $slug,
                'is_enabled' => $record ? (bool) $record->is_enabled : false,
                'installed_at' => $record ? $record->installed_at : null,
                'in_database' => (bool) $record,
                'screenshot' => $screenshotUrl,
                'requires' => $requires,
                'missing_dependencies' => $missingDependencies,
                'has_readme' => file_exists($dir . '/README.md'),
                'has_changelog' => file_exists($dir . '/CHANGELOG.md'),
            ]);
        }

        return $plugins;
    }

    public function enabled(): array
    {
        return array_filter($this->all(), fn($p) => $p['is_enabled']);
    }

    public function getInfo(string $slug): ?array
    {
        $infoPath = base_path("plugins/{$slug}/info.json");
        if (!file_exists($infoPath)) {
            return null;
        }
        return json_decode(file_get_contents($infoPath), true);
    }

    public function checkDependencies(string $slug): array
    {
        $info = $this->getInfo($slug);
        $requires = $info['requires'] ?? [];
        $missing = [];
        foreach ($requires as $dep) {
            $record = Plugin::where('slug', $dep)->where('is_enabled', true)->first();
            if (!$record) {
                $missing[] = $dep;
            }
        }
        return $missing;
    }

    public function getDependents(string $slug): array
    {
        $dependents = [];
        $pluginsDir = base_path('plugins');
        if (!is_dir($pluginsDir)) {
            return [];
        }

        $installedSlugs = Plugin::where('is_enabled', true)->pluck('slug')->toArray();

        foreach ($installedSlugs as $installedSlug) {
            $info = $this->getInfo($installedSlug);
            if ($info && in_array($slug, $info['requires'] ?? [])) {
                $dependents[] = $installedSlug;
            }
        }
        return $dependents;
    }

    public function checkInstallDependencies(array $info): array
    {
        $requires = $info['requires'] ?? [];
        $missing = [];
        foreach ($requires as $dep) {
            $record = Plugin::where('slug', $dep)->first();
            if (!$record) {
                $depInfoPath = base_path("plugins/{$dep}/info.json");
                if (file_exists($depInfoPath)) {
                    $depInfo = json_decode(file_get_contents($depInfoPath), true);
                    $missing[] = $depInfo['name'] ?? $dep;
                } else {
                    $missing[] = $dep;
                }
            }
        }
        return $missing;
    }

    public function install(string $slug): void
    {
        $infoPath = base_path("plugins/{$slug}/info.json");

        if (!file_exists($infoPath)) {
            throw new \RuntimeException("Plugin info.json not found for slug: {$slug}");
        }

        Plugin::updateOrCreate(
            ['slug' => $slug],
            ['is_enabled' => false, 'installed_at' => now()]
        );

        $migrationsPath = base_path("plugins/{$slug}/database/migrations");
        if (is_dir($migrationsPath)) {
            $migrator = app(Migrator::class);
            $migrator->run([$migrationsPath]);
        }

        $this->publishAssets($slug);
    }

    public function publishAssets(string $slug): void
    {
        $pluginDir = base_path("plugins/{$slug}");
        $publicDir = public_path("plugins/{$slug}");

        if (!is_dir($publicDir)) {
            File::makeDirectory($publicDir, 0755, true);
        }

        foreach (['screenshot.jpg', 'screenshot.png', 'screenshot.webp'] as $file) {
            $src = $pluginDir . '/' . $file;
            if (file_exists($src)) {
                File::copy($src, $publicDir . '/' . $file);
            }
        }

        $assetsDir = $pluginDir . '/assets';
        if (is_dir($assetsDir)) {
            $publicAssetsDir = $publicDir . '/assets';
            if (is_dir($publicAssetsDir)) {
                File::deleteDirectory($publicAssetsDir);
            }
            File::copyDirectory($assetsDir, $publicAssetsDir);
        }
    }

    public function uninstall(string $slug): void
    {
        $dependents = $this->getDependents($slug);
        if (!empty($dependents)) {
            throw new \RuntimeException(__('Cannot uninstall: the following plugins depend on this one: :plugins', ['plugins' => implode(', ', $dependents)]));
        }

        $migrationsPath = base_path("plugins/{$slug}/database/migrations");
        if (is_dir($migrationsPath)) {
            $migrator = app(Migrator::class);
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                $migrator->reset([$migrationsPath]);
            } finally {
                \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        Plugin::where('slug', $slug)->delete();

        $pluginDir = base_path("plugins/{$slug}");
        if (is_dir($pluginDir)) {
            File::deleteDirectory($pluginDir);
        }

        $publicDir = public_path("plugins/{$slug}");
        if (is_dir($publicDir)) {
            File::deleteDirectory($publicDir);
        }
    }

    public function enable(string $slug): void
    {
        $missing = $this->checkDependencies($slug);
        if (!empty($missing)) {
            throw new \RuntimeException(__('Cannot enable: required plugins are not installed/enabled: :plugins', ['plugins' => implode(', ', $missing)]));
        }
        Plugin::where('slug', $slug)->update(['is_enabled' => true]);
        $this->notifyNodeReload();
    }

    public function disable(string $slug): void
    {
        $dependents = $this->getDependents($slug);
        if (!empty($dependents)) {
            throw new \RuntimeException(__('Cannot disable: the following plugins depend on this one: :plugins', ['plugins' => implode(', ', $dependents)]));
        }
        Plugin::where('slug', $slug)->update(['is_enabled' => false]);
        $this->notifyNodeReload();
    }

    protected function notifyNodeReload(): void
    {
        try {
            Http::timeout(5)->post('http://localhost:' . env('PORT_NODE') . '/backend-reload-plugins');
        } catch (\Throwable $e) {
        }
    }

    public function validateInfoJson(array $info): bool
    {
        $required = ['name', 'slug', 'version', 'author', 'compatibility', 'entry'];
        foreach ($required as $field) {
            if (empty($info[$field])) {
                return false;
            }
        }
        return true;
    }

    protected function studlySlug(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug)));
    }
}
