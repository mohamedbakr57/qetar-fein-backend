<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Services\DelayEstimationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TripController extends Controller
{
    use ApiResponse;

    protected DelayEstimationService $delayEstimationService;

    public function __construct(DelayEstimationService $delayEstimationService)
    {
        $this->delayEstimationService = $delayEstimationService;
    }

    /**
     * Estimate delay for a trip based on passenger GPS and community reports
     */
    public function estimateDelay(int $tripId): JsonResponse
    {
        $result = $this->delayEstimationService->estimateDelay($tripId);

        if (isset($result['error'])) {
            return $this->errorResponse($result['error'], 404);
        }

        return $this->apiResponse($result, 'Delay estimation completed');
    }
}
