<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\CategoryResource;
use App\Http\Resources\Api\LocationResource;
use App\Http\Resources\Api\SupplierResource;
use App\Http\Resources\Api\UnitResource;
use App\Http\Resources\Api\WarehouseResource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterDataController extends ApiController
{
    protected function paginate($query, Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 15)));

        return $query->paginate($perPage)->withQueryString();
    }

    public function categories(Request $request): JsonResponse
    {
        $query = Category::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $inner->where('code', 'like', $term)->orWhere('name', 'like', $term);
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name');

        return $this->paginated(CategoryResource::collection($this->paginate($query, $request)));
    }

    public function units(Request $request): JsonResponse
    {
        $query = Unit::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $inner->where('code', 'like', $term)->orWhere('name', 'like', $term);
            }))
            ->orderBy('name');

        return $this->paginated(UnitResource::collection($this->paginate($query, $request)));
    }

    public function suppliers(Request $request): JsonResponse
    {
        $query = Supplier::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $inner->where('code', 'like', $term)->orWhere('name', 'like', $term);
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name');

        return $this->paginated(SupplierResource::collection($this->paginate($query, $request)));
    }

    public function customerPrices(Request $request, Customer $customer): JsonResponse
    {
        $query = $customer->itemPrices()
            ->with('item:id,sku,name')
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $q->whereHas('item', fn ($inner) => $inner->where('sku', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('item_id')
            ->orderBy('min_quantity');

        $paginator = $this->paginate($query, $request);

        $paginator->getCollection()->transform(fn ($r) => [
            'id' => $r->id,
            'item_id' => $r->item_id,
            'sku' => $r->item?->sku,
            'item_name' => $r->item?->name,
            'min_quantity' => (int) $r->min_quantity,
            'price' => (float) $r->price,
            'notes' => $r->notes,
        ]);

        return $this->paginated($paginator);
    }

    public function customers(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $inner->where('code', 'like', $term)->orWhere('name', 'like', $term);
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name');

        $paginator = $this->paginate($query, $request);

        $paginator->getCollection()->transform(fn ($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'type' => $c->type,
            'contact_person' => $c->contact_person,
            'phone' => $c->phone,
            'email' => $c->email,
            'address' => $c->address,
            'status' => $c->status,
        ]);

        return $this->paginated($paginator);
    }

    public function warehouses(Request $request): JsonResponse
    {
        $query = Warehouse::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $inner->where('code', 'like', $term)->orWhere('name', 'like', $term);
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name');

        return $this->paginated(WarehouseResource::collection($this->paginate($query, $request)));
    }

    public function locs(Request $request): JsonResponse
    {
        $query = Location::query()
            ->with('rack.zone.warehouse')
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $inner->where('code', 'like', $term)->orWhere('name', 'like', $term);
            }))
            ->orderBy('code');

        return $this->paginated(LocationResource::collection($this->paginate($query, $request)));
    }
}
