<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Exceptions\ModuleException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Nwidart\Modules\Facades\Module as ModuleFacade;
use Nwidart\Modules\Module;
use App\Models\Module as ModuleModel;

class ModuleService
{
    public string $modulesPath;

    public string $modulesStatusesPath;

    public function __construct()
    {
        $this->modulesPath = base_path('Modules');
        $this->modulesStatusesPath = base_path('modules_statuses.json');
    }

    public function findModuleByName(string $moduleName): ?Module
    {
        return ModuleFacade::find(strtolower($moduleName));
    }

    public function getModuleByName(string $moduleName): ?ModuleModel
    {
        $module = $this->findModuleByName($moduleName);
        if (! $module) {
            return null;
        }

        $moduleData = json_decode(File::get($module->getPath() . '/module.json'), true);
        $moduleStatuses = $this->getModuleStatuses();

        return new ModuleModel([
            'id' => $module->getName(),
            'name' => $module->getName(),
            'title' => $moduleData['name'] ?? $module->getName(),
            'description' => $moduleData['description'] ?? '',
            'icon' => $moduleData['icon'] ?? 'bi-box',
            'status' => $moduleStatuses[$module->getName()] ?? false,
            'version' => $moduleData['version'] ?? '1.0.0',
            'tags' => $moduleData['keywords'] ?? [],
        ]);
    }

    /**
     * Get the module statuses from the modules_statuses.json file.
     */
    public function getModuleStatuses(): array
    {
        if (! File::exists(path: $this->modulesStatusesPath)) {
            return [];
        }

        return json_decode(File::get($this->modulesStatusesPath), true) ?? [];
    }

    /**
     * Get all modules from the Modules folder.
     */
    public function getPaginatedModules(int $perPage = 15): LengthAwarePaginator
    {
        $modules = [];
        if (! File::exists($this->modulesPath)) {
            throw new ModuleException(message: __('Modules directory does not exist. Please ensure the "Modules" directory is present in the application root.'));
        }

        $moduleDirectories = File::directories($this->modulesPath);

        foreach ($moduleDirectories as $moduleDirectory) {
            $module = $this->getModuleByName(basename($moduleDirectory));
            if ($module) {
                $modules[] = $module;
            }
        }

        // Manually paginate the array.
        $page = request('page', 1);
        $collection = collect($modules);
        $paged = new LengthAwarePaginator(
            $collection->forPage($page, $perPage),
            $collection->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $paged;
    }

    public function uploadModule(Request $request)
    {
        $file = $request->file('module');
        $filePath = $file->storeAs('modules', $file->getClientOriginalName());

        // Extract and install the module.
        $modulePath = storage_path('app/' . $filePath);
        $zip = new \ZipArchive();

        if (! $zip->open($modulePath)) {
            throw new ModuleException(__('Module upload failed. The file may not be a valid zip archive.'));
        }

        $moduleName = $zip->getNameIndex(0); // Retrieve the module folder name before closing
        $zip->extractTo($this->modulesPath);
        $zip->close();

        // Check valid module structure.
        $moduleName = str_replace('/', '', $moduleName);
        if (! File::exists($this->modulesPath . '/' . $moduleName . '/module.json')) {
            throw new ModuleException(__('Failed to find the module in the system. Please ensure the module has a valid module.json file.'));
        }

        // Save this module to the modules_statuses.json file.
        $moduleStatuses = $this->getModuleStatuses();
        $moduleStatuses[$moduleName] = true;
        File::put($this->modulesStatusesPath, json_encode($moduleStatuses, JSON_PRETTY_PRINT));

        // Clear the cache.
        Artisan::call('cache:clear');

        return true;
    }

    public function toggleModule($moduleName, $enable = true): bool
    {
        try {
            // Clear the cache.
            Artisan::call('cache:clear');

            // Activate/Deactivate the module.
            $callbackName = $enable ? 'module:enable' : 'module:disable';
            Artisan::call($callbackName, ['module' => $moduleName]);
        } catch (\Throwable $th) {
            Log::error("Failed to toggle module {$moduleName}: " . $th->getMessage());
            throw new ModuleException(__('Failed to toggle module status. Please check the logs for more details.'));
        }

        return true;
    }

    public function toggleModuleStatus(string $moduleName): bool
    {
        $moduleStatuses = $this->getModuleStatuses();

        if (! isset($moduleStatuses[$moduleName]) && ! File::exists($this->modulesPath . '/' . $moduleName)) {
            throw new ModuleException(__('Module not found.'));
        }

        // Just enable it first so that it would be in the getModuleStatuses()
        if (! isset($moduleStatuses[$moduleName])) {
            Artisan::call('module:enable', ['module' => $moduleName]);
            $moduleStatuses = $this->getModuleStatuses();
        }

        // Toggle the status.
        $moduleStatuses[$moduleName] = ! $moduleStatuses[$moduleName];

        // Save the updated statuses.
        File::put($this->modulesStatusesPath, json_encode($moduleStatuses, JSON_PRETTY_PRINT));

        $this->toggleModule($moduleName, ! empty($moduleStatuses[$moduleName]));

        return $moduleStatuses[$moduleName];
    }

    public function deleteModule(string $moduleName): void
    {
        $module = $this->findModuleByName($moduleName);

        if (! $module) {
            throw new ModuleException(__('Module not found.'), Response::HTTP_NOT_FOUND);
        }

        // Disable the module before deletion.
        Artisan::call('module:disable', ['module' => $module->getName()]);

        // Remove the module files.
        $modulePath = base_path('Modules/' . $module->getName());

        if (! is_dir($modulePath)) {
            throw new ModuleException(__('Module directory does not exist. Please ensure the module is installed correctly.'));
        }

        // Delete the module from the database.
        ModuleFacade::delete($module->getName());

        // Clear the cache.
        Artisan::call('cache:clear');
    }
}
