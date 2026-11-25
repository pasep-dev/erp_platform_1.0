<?php

namespace App\Http\Controllers;

use App\Enums\NumberingModuleCode;
use App\Models\NumberingModule;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NumberingPreviewController extends Controller
{
    public function __invoke(
        Request $request,
        NumberingModule $numberingModule,
        NumberingService $numberingService,
    ): JsonResponse {
        $moduleCode = NumberingModuleCode::tryFrom($numberingModule->code);
        abort_if($moduleCode === null, 404);

        $user = $request->user();
        abort_unless(
            $user instanceof User
                && (
                    $user->hasGlobalDepartmentAccess()
                    || $user->can($moduleCode->previewPermission())
                ),
            403,
        );

        $parameters = $request->query('parameters', []);
        abort_unless(is_array($parameters), 422);

        $parameters = array_filter(
            $parameters,
            static fn (mixed $value): bool => is_scalar($value) || $value === null,
        );

        return response()->json([
            'enabled' => $numberingService->hasActiveDocument($moduleCode),
            'preview' => $numberingService->preview($moduleCode, $parameters),
        ]);
    }
}
