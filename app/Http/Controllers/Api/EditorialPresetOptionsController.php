<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Generation\Presets\EditorialPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class EditorialPresetOptionsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => collect(EditorialPreset::cases())
                ->map(fn (EditorialPreset $p) => ['value' => $p->value, 'label' => $p->label()])
                ->all(),
        ]);
    }
}
