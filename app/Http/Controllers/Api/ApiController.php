<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Support\WarehouseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class ApiController extends Controller
{
    /**
     * Warehouse ids the current token owner may access.
     *
     * @return array<int, int>
     */
    protected function warehouseIds(): array
    {
        return WarehouseAccess::ids();
    }

    /**
     * Constrain a query to the current user's accessible warehouses.
     */
    protected function scopeWarehouse(Builder $query, string $column = 'warehouse_id'): Builder
    {
        return $query->whereIn($column, $this->warehouseIds());
    }

    /**
     * Constrain a query to warehouses accessible on either side of a transfer.
     */
    protected function scopeTransferWarehouses(Builder $query): Builder
    {
        $ids = $this->warehouseIds();

        return $query->where(function (Builder $inner) use ($ids): void {
            $inner->whereIn('from_warehouse_id', $ids)->orWhereIn('to_warehouse_id', $ids);
        });
    }

    /**
     * Abort with 403 unless the current user may access the warehouse.
     */
    protected function authorizeWarehouse(int|string|null $warehouseId): void
    {
        $ids = $this->warehouseIds();

        if ($warehouseId === null || ! in_array((int) $warehouseId, $ids, true)) {
            abort(403, __('Anda tidak memiliki akses ke gudang ini.'));
        }
    }

    /**
     * Find a record only within the current user's accessible warehouses.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    protected function findAccessible(string $model, int|string $id, string $column = 'warehouse_id'): Model
    {
        return $this->scopeWarehouse($model::query(), $column)->findOrFail($id);
    }

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
     * @param  LengthAwarePaginator<int,mixed>|ResourceCollection  $paginator
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
