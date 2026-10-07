<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class ApiController extends Controller
{
    protected function ok(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    /**
     * @param  LengthAwarePaginator<mixed>|ResourceCollection  $paginator
     */
    protected function paginated(mixed $paginator, string $message = 'OK'): JsonResponse
    {
        $resource = $paginator instanceof ResourceCollection
            ? $paginator->resource
            : $paginator;

        $payload = $paginator instanceof ResourceCollection
            ? $paginator->resolve()
            : $paginator->items();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $payload,
            'meta' => [
                'current_page' => $resource->currentPage(),
                'per_page' => $resource->perPage(),
                'total' => $resource->total(),
                'last_page' => $resource->lastPage(),
                'from' => $resource->firstItem(),
                'to' => $resource->lastItem(),
            ],
        ]);
    }

    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], fn ($value) => $value !== null), $status);
    }
}
