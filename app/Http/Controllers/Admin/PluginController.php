<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PluginManager;
use App\Services\UpdateCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class PluginController extends Controller
{
    protected PluginManager $pluginManager;
    protected UpdateCheckService $updateCheckService;

    public function __construct(PluginManager $pluginManager, UpdateCheckService $updateCheckService)
    {
        $this->pluginManager = $pluginManager;
        $this->updateCheckService = $updateCheckService;
    }

    public function index()
    {
        $plugins = $this->pluginManager->all();
        $pendingReplace = Session::get('plugin_pending_replace');

        $status = $this->updateCheckService->getStatus();
        $pluginUpdates = $status['plugin_updates'];

        usort($plugins, function ($a, $b) use ($pluginUpdates) {
            $aHasUpdate = isset($pluginUpdates[$a['slug']]) ? 0 : 1;
            $bHasUpdate = isset($pluginUpdates[$b['slug']]) ? 0 : 1;
            return $aHasUpdate - $bHasUpdate;
        });

        return view('theme::pages.admin.plugins.index', compact('plugins', 'pendingReplace', 'pluginUpdates'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'plugin_zip' => 'required|file|mimes:zip|max:51200',
        ]);

        $zipFile = $request->file('plugin_zip');
        $tempDir = storage_path('app/plugin_uploads/' . uniqid('plugin_', true));

        try {
            File::makeDirectory($tempDir, 0755, true);

            $zip = new \ZipArchive();
            $result = $zip->open($zipFile->getPathname());

            if ($result !== true) {
                File::deleteDirectory($tempDir);
                return redirect()->back()->with('alert', [
                    'type' => 'danger',
                    'msg' => __('Failed to open zip archive.'),
                ]);
            }

            $zip->extractTo($tempDir);
            $zip->close();

            $infoPath = $this->findInfoJson($tempDir);

            if (!$infoPath) {
                File::deleteDirectory($tempDir);
                return redirect()->back()->with('alert', [
                    'type' => 'danger',
                    'msg' => __('Invalid plugin: info.json not found in the zip archive.'),
                ]);
            }

            $info = json_decode(file_get_contents($infoPath), true);

            if (!$info || !$this->pluginManager->validateInfoJson($info)) {
                File::deleteDirectory($tempDir);
                return redirect()->back()->with('alert', [
                    'type' => 'danger',
                    'msg' => __('Invalid plugin: info.json is missing required fields (name, slug, version, author, compatibility, entry).'),
                ]);
            }

            $missingDeps = $this->pluginManager->checkInstallDependencies($info);
            if (!empty($missingDeps)) {
                File::deleteDirectory($tempDir);
                return redirect()->back()->with('alert', [
                    'type' => 'danger',
                    'msg' => __('Cannot install: the following required plugins must be installed first: :plugins', ['plugins' => implode(', ', $missingDeps)]),
                ]);
            }

            $slug = $info['slug'];
            $targetDir = base_path("plugins/{$slug}");

            if (is_dir($targetDir)) {
                $existingInfoPath = $targetDir . '/info.json';
                $existingInfo = file_exists($existingInfoPath)
                    ? (json_decode(file_get_contents($existingInfoPath), true) ?? [])
                    : [];

                if (($existingInfo['version'] ?? '') === ($info['version'] ?? '')) {
                    $pendingKey = 'plugin_upload_' . $slug;
                    $pluginExtractedDir = dirname($infoPath);
                    $pendingDir = storage_path('app/plugin_pending/' . $slug);

                    if (is_dir($pendingDir)) {
                        File::deleteDirectory($pendingDir);
                    }
                    File::makeDirectory($pendingDir, 0755, true);
                    File::copyDirectory($pluginExtractedDir, $pendingDir);
                    File::deleteDirectory($tempDir);

                    Session::put('plugin_pending_replace', [
                        'slug' => $slug,
                        'existing' => $existingInfo,
                        'incoming' => $info,
                    ]);

                    return redirect()->route('admin.plugins.index');
                }
            }

            $pluginExtractedDir = dirname($infoPath);

            if (is_dir($targetDir)) {
                File::deleteDirectory($targetDir);
            }

            File::copyDirectory($pluginExtractedDir, $targetDir);
            File::deleteDirectory($tempDir);

            $this->pluginManager->install($slug);

            return redirect()->route('admin.plugins.index')->with('alert', [
                'type' => 'success',
                'msg' => __('Plugin ":name" installed successfully.', ['name' => $info['name']]),
            ]);

        } catch (\Throwable $e) {
            if (is_dir($tempDir)) {
                File::deleteDirectory($tempDir);
            }

            return redirect()->back()->with('alert', [
                'type' => 'danger',
                'msg' => __('Plugin installation failed: :error', ['error' => $e->getMessage()]),
            ]);
        }
    }

    public function replaceConfirm(Request $request)
    {
        $pending = Session::get('plugin_pending_replace');

        if (!$pending || $request->input('action') !== 'replace') {
            Session::forget('plugin_pending_replace');
            return redirect()->route('admin.plugins.index');
        }

        $slug = $pending['slug'];
        $pendingDir = storage_path('app/plugin_pending/' . $slug);
        $targetDir = base_path("plugins/{$slug}");

        try {
            if (!is_dir($pendingDir)) {
                Session::forget('plugin_pending_replace');
                return redirect()->route('admin.plugins.index')->with('alert', [
                    'type' => 'danger',
                    'msg' => __('Pending plugin files not found.'),
                ]);
            }

            if (is_dir($targetDir)) {
                File::deleteDirectory($targetDir);
            }

            File::copyDirectory($pendingDir, $targetDir);
            File::deleteDirectory($pendingDir);

            $this->pluginManager->install($slug);

            Session::forget('plugin_pending_replace');

            return redirect()->route('admin.plugins.index')->with('alert', [
                'type' => 'success',
                'msg' => __('Plugin ":name" replaced successfully.', ['name' => $pending['incoming']['name']]),
            ]);
        } catch (\Throwable $e) {
            Session::forget('plugin_pending_replace');
            return redirect()->route('admin.plugins.index')->with('alert', [
                'type' => 'danger',
                'msg' => __('Plugin replacement failed: :error', ['error' => $e->getMessage()]),
            ]);
        }
    }

    public function enable(string $slug)
    {
        try {
            $exists = \App\Models\Plugin::where('slug', $slug)->exists();
            if (!$exists) {
                $this->pluginManager->install($slug);
            }
            $this->pluginManager->enable($slug);

            return redirect()->route('admin.plugins.index')->with('alert', [
                'type' => 'success',
                'msg' => __('Plugin enabled successfully.'),
            ]);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.plugins.index')->with('alert', [
                'type' => 'danger',
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function disable(string $slug)
    {
        try {
            $this->pluginManager->disable($slug);

            return redirect()->route('admin.plugins.index')->with('alert', [
                'type' => 'success',
                'msg' => __('Plugin disabled successfully.'),
            ]);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.plugins.index')->with('alert', [
                'type' => 'danger',
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(string $slug)
    {
        try {
            $this->pluginManager->uninstall($slug);

            return redirect()->route('admin.plugins.index')->with('alert', [
                'type' => 'success',
                'msg' => __('Plugin uninstalled successfully.'),
            ]);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.plugins.index')->with('alert', [
                'type' => 'danger',
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function marketplace()
    {
        $installedPlugins = collect($this->pluginManager->all())->keyBy('slug');

        try {
            $response = Http::timeout(10)->get('https://mpwa.onexgen.com/plugins/all.json');
            if ($response->successful()) {
                $marketplacePlugins = $response->json() ?? [];
            } else {
                $marketplacePlugins = [];
            }
        } catch (\Throwable $e) {
            $marketplacePlugins = [];
        }

        return view('theme::pages.admin.plugins.marketplace', compact('marketplacePlugins', 'installedPlugins'));
    }

    public function marketplaceInstall(Request $request)
    {
        $request->validate([
            'download_url' => 'required|url',
            'slug' => 'required|string',
        ]);

        $tempDir = storage_path('app/plugin_uploads/' . uniqid('plugin_', true));

        try {
            File::makeDirectory($tempDir, 0755, true);

            $response = Http::timeout(60)->get($request->input('download_url'));

            if (!$response->successful()) {
                File::deleteDirectory($tempDir);
                return redirect()->back()->with('alert', [
                    'type' => 'danger',
                    'msg' => __('Failed to download plugin from marketplace.'),
                ]);
            }

            $zipPath = $tempDir . '/plugin.zip';
            file_put_contents($zipPath, $response->body());

            $extractDir = $tempDir . '/extracted';
            File::makeDirectory($extractDir, 0755, true);

            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                File::deleteDirectory($tempDir);
                return redirect()->back()->with('alert', [
                    'type' => 'danger',
                    'msg' => __('Failed to open downloaded plugin archive.'),
                ]);
            }

            $zip->extractTo($extractDir);
            $zip->close();

            $infoPath = $this->findInfoJson($extractDir);

            if (!$infoPath) {
                File::deleteDirectory($tempDir);
                return redirect()->back()->with('alert', [
                    'type' => 'danger',
                    'msg' => __('Invalid plugin: info.json not found.'),
                ]);
            }

            $info = json_decode(file_get_contents($infoPath), true);

            if (!$info || !$this->pluginManager->validateInfoJson($info)) {
                File::deleteDirectory($tempDir);
                return redirect()->back()->with('alert', [
                    'type' => 'danger',
                    'msg' => __('Invalid plugin: info.json is missing required fields.'),
                ]);
            }

            $missingDeps = $this->pluginManager->checkInstallDependencies($info);
            if (!empty($missingDeps)) {
                File::deleteDirectory($tempDir);
                return redirect()->back()->with('alert', [
                    'type' => 'danger',
                    'msg' => __('Cannot install: the following required plugins must be installed first: :plugins', ['plugins' => implode(', ', $missingDeps)]),
                ]);
            }

            $slug = $info['slug'];
            $pluginExtractedDir = dirname($infoPath);
            $targetDir = base_path("plugins/{$slug}");

            if (is_dir($targetDir)) {
                File::deleteDirectory($targetDir);
            }

            File::copyDirectory($pluginExtractedDir, $targetDir);
            File::deleteDirectory($tempDir);

            $this->pluginManager->install($slug);

            return redirect()->back()->with('alert', [
                'type' => 'success',
                'msg' => __('Plugin ":name" installed from marketplace successfully.', ['name' => $info['name']]),
            ]);

        } catch (\Throwable $e) {
            if (is_dir($tempDir)) {
                File::deleteDirectory($tempDir);
            }

            return redirect()->back()->with('alert', [
                'type' => 'danger',
                'msg' => __('Marketplace installation failed: :error', ['error' => $e->getMessage()]),
            ]);
        }
    }

    public function pluginFile(string $slug, string $filename)
    {
        $allowed = ['README.md', 'CHANGELOG.md'];
        if (!in_array($filename, $allowed)) {
            return response()->json(['error' => __('Not found')], 404);
        }

        $path = base_path("plugins/{$slug}/{$filename}");
        if (!file_exists($path)) {
            return response()->json(['error' => __('Not found')], 404);
        }

        $content = file_get_contents($path);
        $html = \Illuminate\Support\Str::markdown($content);

        return response()->json(['html' => $html]);
    }

    protected function findInfoJson(string $dir): ?string
    {
        $directPath = $dir . '/info.json';
        if (file_exists($directPath)) {
            return $directPath;
        }

        foreach (File::directories($dir) as $subDir) {
            $subPath = $subDir . '/info.json';
            if (file_exists($subPath)) {
                return $subPath;
            }
        }

        return null;
    }
}
