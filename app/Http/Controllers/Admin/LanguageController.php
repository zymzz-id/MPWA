<?php
/*
Copyright © Magd Almuntaser, OneXGen Technology. All rights reserved.
Project: MPWA Whatsapp Gateway | Multi Device
Licensed under the CC BY-NC-ND 4.0 License.
For details, visit https://creativecommons.org/licenses/by-nc-nd/4.0/.
*/

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\Intl\Locales;

class LanguageController extends Controller
{
    private $langPath = 'resources/lang';
	public $baseLang = 'en';

    public function index()
	{
		
		$baseLang = $this->baseLang;
		$baseFilePath = base_path("{$this->langPath}/{$baseLang}.json");
		$baseData = File::exists($baseFilePath) ? json_decode(File::get($baseFilePath), true) : [];
		$supportedLocales = LaravelLocalization::getSupportedLocales();

		$allLanguages = collect(File::files(base_path($this->langPath)))
			->filter(function ($file) {
				return $file->getExtension() === 'json';
			})
			->map(function ($file) {
				return pathinfo($file->getFilename(), PATHINFO_FILENAME);
			})
			->toArray();

		$progressData = [];
		foreach ($allLanguages as $lang) {
			$filePath = base_path("{$this->langPath}/{$lang}.json");
			$data = File::exists($filePath) ? json_decode(File::get($filePath), true) : [];
			if ($lang === $baseLang) {
				$progressData[$lang] = [
					'translated' => count($baseData),
					'remaining' => 0,
					'percentage' => 100,
				];
				continue;
			}
			$missingKeys = array_diff_key($baseData, $data);
			$untranslatedCount = count($missingKeys);
			foreach ($baseData as $key => $baseValue) {
				if (isset($data[$key]) && $data[$key] === $baseValue) {
					$untranslatedCount++;
				}
			}
			$translatedCount = count($baseData) - $untranslatedCount;
			$totalCount = count($baseData);
			$progressData[$lang] = [
				'translated' => $translatedCount,
				'remaining' => $untranslatedCount,
				'percentage' => $totalCount > 0 ? round(($translatedCount / $totalCount) * 100) : 100,
			];
		}

		$languages = collect($allLanguages)
			->filter(function ($lang) use ($baseLang) {
				return $lang !== $baseLang;
			})
			->sort(function ($a, $b) use ($progressData) {
				return $progressData[$b]['percentage'] - $progressData[$a]['percentage'];
			})
			->prepend($baseLang)
			->values()
			->toArray();
			
		$allLocales = Locales::getNames(app()->getLocale());
		$filteredLanguages = [];
		foreach ($allLocales as $code => $name) {
			$baseCode = explode('_', $code)[0];

			if (!array_key_exists($baseCode, $filteredLanguages)) {
				$filteredLanguages[$baseCode] = $name;
			}
		}

		$existingLanguages = array_keys(LaravelLocalization::getSupportedLocales());

		return view('theme::pages.admin.languages.index', compact('filteredLanguages', 'existingLanguages', 'supportedLocales', 'baseLang', 'languages', 'progressData'));
	}

	public function add(Request $request)
	{
		$baseLang = $this->baseLang;
		$language = $request->input('language');
		$baseFilePath = base_path("{$this->langPath}/{$baseLang}.json");
		$newFilePath = base_path("{$this->langPath}/{$language}.json");
		
		$baseIndexFilePath = base_path("{$this->langPath}/index/{$baseLang}.json");
		$indexFilePath = base_path("{$this->langPath}/index/{$language}.json");
		
		$configFilePath = config_path('laravellocalization.php');

		if (!File::exists($baseFilePath)) {
			return response()->json(['success' => false, 'message' => 'Base language file not found'], 404);
		}

		if (File::exists($newFilePath)) {
			return response()->json(['success' => false, 'message' => 'Language already exists']);
		}

		try {
			File::copy($baseFilePath, $newFilePath);
			File::copy($baseIndexFilePath, $indexFilePath);

			if (File::exists($configFilePath)) {
				$configContent = File::get($configFilePath);
				$pattern = "/\/\/('{$language}'\s*=>\s*\[.*?\]),/s";
				$replacement = "$1,";
				$updatedContent = preg_replace($pattern, $replacement, $configContent);

				if ($updatedContent !== null) {
					File::put($configFilePath, $updatedContent);
				} else {
					throw new \Exception("Failed to update the configuration file");
				}
			}

			return response()->json(['success' => true, 'message' => 'Language added successfully']);
		} catch (\Exception $e) {
			return response()->json(['success' => false, 'message' => 'Failed to add language: ' . $e->getMessage()], 500);
		}
	}

    public function edit($lang, Request $request)
	{
		$baseLang = $this->baseLang;
		$getName = Locales::getName($lang, app()->getLocale());
		$baseFilePath = base_path("{$this->langPath}/{$baseLang}.json");
		$baseData = File::exists($baseFilePath) ? json_decode(File::get($baseFilePath), true) : [];
		$filePath = base_path("{$this->langPath}/{$lang}.json");
		$data = File::exists($filePath) ? json_decode(File::get($filePath), true) : [];
		
		$translations = collect($baseData)
			->map(function ($value, $key) use ($data) {
				return [
					'key' => $key,
					'value' => $data[$key] ?? '',
					'is_translated' => isset($data[$key]) && $data[$key] !== $value,
					'is_empty' => !isset($data[$key]) || empty($data[$key])
				];
			})
			->sortBy(function ($item) {
				if ($item['is_empty']) return 1;
				if (!$item['is_translated']) return 2;
				return 3;
			});

		$perPage = 15;
		$page = $request->input('page', 1);
		$paginatedTranslations = $this->paginate($translations, $perPage, $page, 'page');
		
		return view('theme::pages.admin.languages.edit', compact('getName', 'lang', 'paginatedTranslations'));
	}

	public function update(Request $request, $lang)
	{
		$baseLang = $this->baseLang;
		$baseFilePath = base_path("{$this->langPath}/{$baseLang}.json");
		$filePath = base_path("{$this->langPath}/{$lang}.json");

		if (!File::exists($baseFilePath)) {
			return response()->json(['message' => __('Base language file not found')], 404);
		}

		if (!File::exists($filePath)) {
			return response()->json(['message' => __('Language file not found')], 404);
		}

		$baseData = json_decode(File::get($baseFilePath), true);
		$existingData = json_decode(File::get($filePath), true);

		$translations = $request->input('translations', []);
		foreach ($translations as $key => $value) {
			if (array_key_exists($key, $baseData)) {
				$existingData[$key] = $value;
			}
		}

		try {
			File::put($filePath, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
			return response()->json(['message' => __('Translations updated successfully')]);
		} catch (\Exception $e) {
			return response()->json(['message' => __('Failed to update translations: :error' , ['error' => $e->getMessage()])], 500);
		}
	}
	
	public function destroy($lang)
	{
		if (strtolower($lang) === $this->baseLang) {
			return redirect()->back()->with('error', __('Cannot delete base language'));
		}

		$filePath = base_path("{$this->langPath}/{$lang}.json");
		$indexFilePath = base_path("{$this->langPath}/index/{$lang}.json");
		$configFilePath = config_path('laravellocalization.php');

		if (File::exists($filePath)) {
			try {
				File::delete($filePath);
				File::delete($indexFilePath);

				if (File::exists($configFilePath)) {
					$configContent = File::get($configFilePath);
					$pattern = "/('{$lang}'\s*=>\s*\[.*?\]),/s";
					$replacement = "//$1,";
					$updatedContent = preg_replace($pattern, $replacement, $configContent);

					if ($updatedContent !== null) {
						File::put($configFilePath, $updatedContent);
					} else {
						throw new \Exception("Failed to update the configuration file");
					}
				}

				return redirect()->back()->with('success', __('Language deleted successfully'));
			} catch (\Exception $e) {
				return redirect()->back()->with('error', __('Failed to delete language: ') . $e->getMessage());
			}
		}

		return redirect()->back()->with('error', __('Language file not found'));
	}

    private function paginate(Collection $items, $perPage, $currentPage, $pageName)
    {
        $currentItems = $items->forPage($currentPage, $perPage);

        return new LengthAwarePaginator(
            $currentItems,
            $items->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => $pageName]
        );
    }
}
