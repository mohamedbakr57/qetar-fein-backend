<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Train\TrainType;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TrainTypeController extends Controller
{
    use ApiResponse;

    /**
     * Get all train types
     */
    public function index(Request $request): JsonResponse
    {
        $trainTypes = TrainType::orderBy('name')->get();

        return $this->apiResponse([
            'train_types' => $trainTypes,
            'count' => $trainTypes->count()
        ]);
    }

    /**
     * Get train type details
     */
    public function show(int $id): JsonResponse
    {
        $trainType = TrainType::find($id);

        if (!$trainType) {
            return $this->errorResponse('Train type not found', 404);
        }

        // Get trains count for this type
        $trainsCount = \App\Models\Train\Train::where('train_type_id', $id)->count();

        return $this->apiResponse([
            'train_type' => $trainType,
            'trains_count' => $trainsCount
        ]);
    }
}
