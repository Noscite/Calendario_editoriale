<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Enums\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class DeontologicalOptionsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Sector::regulatedOptions(),
        ]);
    }
}
