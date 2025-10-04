<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    public function apiResponse($data = null, $message = 'success', $statusCode = 200, $mergedData = []): JsonResponse
    {
        return response()->json(array_merge([
            'status_code' => $statusCode,
            'message' => $message ?? 'success',
            'data' => $data,
        ], $mergedData), $statusCode);
    }

    public function unhandledErrorResponse($error, $statusCode = 500, $data = []): JsonResponse
    {
        if (! is_numeric($statusCode) || $statusCode < 100 || $statusCode > 599) {
            $statusCode = 500;
        }
        if (! app()->hasDebugModeEnabled()) {
            if ($statusCode >= 500) {
                $error = __('api.error');
            }
            $data = [];
        }

        return $this->errorResponse($error, $statusCode, $data);
    }

    public function errorResponse($error, $statusCode = 500, $data = null, $mergedData = []): JsonResponse
    {
        return response()->json(array_merge([
            'status_code' => $statusCode,
            'message' => $error,
            'errors' => $data,
        ], $mergedData), $statusCode);
    }
}
