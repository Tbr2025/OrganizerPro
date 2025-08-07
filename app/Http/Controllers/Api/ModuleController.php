<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\ModuleResource;
use App\Services\Modules\ModuleService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleController extends ApiController
{
    public function __construct(private readonly ModuleService $moduleService)
    {
    }

    /**
     * Modules list.
     *
     * @tags Modules
     */
    #[QueryParameter('per_page', description: 'Number of modules per page.', type: 'int', default: 15, example: 20)]
    public function index(Request $request): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['module.view']);

        $perPage = (int) ($request->input('per_page') ?? config('settings.default_pagination', 15));
        return $this->resourceResponse(
            ModuleResource::collection($this->moduleService->getPaginatedModules($perPage)),
            'Modules retrieved successfully'
        );
    }

    /**
     * Show Module.
     *
     * @tags Modules
     */
    public function show(string $name): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['module.view']);

        $module = $this->moduleService->getModuleByName($name);

        if (! $module) {
            return $this->errorResponse('Module not found', 404);
        }

        return $this->resourceResponse(
            new ModuleResource($module),
            'Module retrieved successfully'
        );
    }

    /**
     * Toggle Module Status.
     *
     * @tags Modules
     */
    public function toggleStatus(Request $request, string $name): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['module.edit']);

        $module = $this->moduleService->getModuleByName($name);
        $previousStatus = $module ? $module->status ?? false : false;

        if (! $module) {
            return $this->errorResponse('Module not found', 404);
        }

        try {
            $this->moduleService->toggleModuleStatus(
                $module->name
            );

            return $this->resourceResponse(
                new ModuleResource($this->moduleService->getModuleByName($name)),
                __('Module status toggled successfully from :previous to :current', [
                    'previous' => $previousStatus ? 'active' : 'inactive',
                    'current' => $module->status ? 'active' : 'inactive',
                ])
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to toggle module status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete Module.
     *
     * @tags Modules
     */
    public function destroy(string $name): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['module.delete']);

        $module = $this->moduleService->getModuleByName($name);

        if (! $module) {
            return $this->errorResponse('Module not found', 404);
        }

        try {
            $this->moduleService->deleteModule($module->name);

            $this->logAction('Module Deleted', $module);

            return $this->successResponse(null, 'Module deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete module: ' . $e->getMessage(), 500);
        }
    }
}
