<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 15)));

        $query = Item::query()
            ->with(['category', 'unit'])
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('sku', 'like', $term)
                        ->orWhere('barcode', 'like', $term)
                        ->orWhere('name', 'like', $term);
                });
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $paginator = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        return $this->paginated(ItemResource::collection($paginator));
    }

    public function show(Item $item): JsonResponse
    {
        $item->loadMissing(['category', 'unit']);

        return $this->ok(new ItemResource($item));
    }
}
