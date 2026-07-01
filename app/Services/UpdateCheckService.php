<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UpdateCheckService
{
    private const CACHE_APP_KEY = 'remote_app_update';
    private const CACHE_PLUGINS_KEY = 'remote_plugins_data';
    private const CACHE_TTL = 300;

    public function getStatus(): array
    {
        $remoteAppData = Cache::remember(self::CACHE_APP_KEY, self::CACHE_TTL, fn() => $this->fetchRemoteAppData());
        $remotePlugins = Cache::remember(self::CACHE_PLUGINS_KEY, self::CACHE_TTL, fn() => $this->fetchRemotePluginsData());

        $newVersion = null;
        if (!empty($remoteAppData['update_available']) && !empty($remoteAppData['new_version'])) {
            $currentVersion = config('app.version');
            if (version_compare($remoteAppData['new_version'], $currentVersion, '>')) {
                $newVersion = $remoteAppData['new_version'];
            }
        }

        $pluginUpdates = [];
        $pluginsDir = base_path('plugins');
        if (!empty($remotePlugins) && is_dir($pluginsDir)) {
            $remoteBySlug = collect($remotePlugins)->keyBy('slug');
            foreach (File::directories($pluginsDir) as $dir) {
                $infoPath = $dir . '/info.json';
                if (!file_exists($infoPath)) continue;
                $info = json_decode(file_get_contents($infoPath), true);
                if (!$info || empty($info['slug'])) continue;
                $remote = $remoteBySlug->get($info['slug']);
                if ($remote && version_compare($remote['version'], $info['version'] ?? '0.0.0', '>')) {
                    $pluginUpdates[$info['slug']] = [
                        'new_version' => $remote['version'],
                        'download_url' => $remote['download_url'] ?? null,
                    ];
                }
            }
        }

        return ['new_version' => $newVersion, 'plugin_updates' => $pluginUpdates];
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_APP_KEY);
        Cache::forget(self::CACHE_PLUGINS_KEY);
    }

    private function fetchRemoteAppData(): array
    {
        try {
            $currentVersion = config('app.version');
            $lang = config('app.locale');
            return Http::timeout(5)->get("https://mpwa.onexgen.com/tools/check.php?v={$currentVersion}&lang={$lang}")->json() ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchRemotePluginsData(): array
    {
        try {
            $response = Http::timeout(5)->get('https://mpwa.onexgen.com/plugins/all.json');
            return $response->successful() ? ($response->json() ?? []) : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
