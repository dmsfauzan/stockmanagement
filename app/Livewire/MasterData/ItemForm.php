<?php

namespace App\Livewire\MasterData;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemBom;
use App\Models\ItemUnitConversion;
use App\Models\Supplier;
use App\Models\SupplierItemPrice;
use App\Models\Unit;
use App\Services\Support\AuditLogger;
use App\Services\Support\ImageService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Form Barang')]
class ItemForm extends Component
{
    use WithFileUploads;

    public ?int $itemId = null;

    public $image = null;

    public bool $removeImage = false;

    public ?string $existingImagePath = null;

    public string $sku = '';

    public string $barcode = '';

    public string $name = '';

    public string $category_id = '';

    public string $unit_id = '';

    public string $brand = '';

    public string $description = '';

    public int $minimum_stock = 0;

    public int $maximum_stock = 0;

    public float $cost = 0;

    public float $price = 0;

    public string $primary_supplier_id = '';

    public string $status = 'active';

    public string $ownership = 'owned';

    public string $consignor_id = '';

    public string $tracking_type = 'none';

    /** @var array<int, array{id:int, unit_id:string, factor:string}> */
    public array $conversionRows = [];

    public string $conversionUnitId = '';

    public string $conversionFactor = '';

    /** @var array<int, array{id:int, component_item_id:string, quantity:string}> */
    public array $bomRows = [];

    public string $bomComponentId = '';

    public string $bomQuantity = '1';

    public function mount($item = null): void
    {
        $model = $item instanceof Item ? $item : ($item ? Item::findOrFail($item) : null);

        if ($model) {
            $this->authorize('update', $model);

            $this->itemId = $model->id;
            $this->sku = (string) $model->sku;
            $this->barcode = (string) ($model->barcode ?? '');
            $this->name = (string) $model->name;
            $this->category_id = (string) $model->category_id;
            $this->unit_id = (string) $model->unit_id;
            $this->brand = (string) ($model->brand ?? '');
            $this->description = (string) ($model->description ?? '');
            $this->minimum_stock = (int) $model->minimum_stock;
            $this->maximum_stock = (int) $model->maximum_stock;
            $this->cost = (float) $model->cost;
            $this->price = (float) $model->price;
            $this->primary_supplier_id = $model->primary_supplier_id ? (string) $model->primary_supplier_id : '';
            $this->status = (string) $model->status;
            $this->ownership = (string) ($model->ownership ?? 'owned');
            $this->consignor_id = $model->consignor_id ? (string) $model->consignor_id : '';
            $this->tracking_type = $model->tracking_type?->value ?? 'none';
            $this->existingImagePath = $model->image_path;

            $this->conversionRows = ItemUnitConversion::where('item_id', $model->id)
                ->orderByDesc('factor')
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'unit_id' => (string) $c->unit_id, 'factor' => (string) $c->factor])
                ->values()->all();

            $this->bomRows = ItemBom::where('kit_item_id', $model->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($b) => ['id' => $b->id, 'component_item_id' => (string) $b->component_item_id, 'quantity' => (string) $b->quantity])
                ->values()->all();
        } else {
            $this->authorize('create', Item::class);
        }
    }

    protected function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:50', Rule::unique('items', 'sku')->ignore($this->itemId)],
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('items', 'barcode')->ignore($this->itemId)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'maximum_stock' => ['required', 'integer', 'gte:minimum_stock'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'primary_supplier_id' => ['nullable', 'exists:suppliers,id'],
            'status' => ['required', 'in:active,inactive'],
            'ownership' => ['required', 'in:owned,consignment'],
            'consignor_id' => ['nullable', 'exists:suppliers,id'],
            'tracking_type' => ['required', 'in:none,batch,serial'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ];
    }

    public function addConversion(): void
    {
        $this->validate([
            'conversionUnitId' => ['required', 'exists:units,id'],
            'conversionFactor' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->conversionRows[] = [
            'id' => 0,
            'unit_id' => (string) $this->conversionUnitId,
            'factor' => (string) $this->conversionFactor,
        ];

        $this->reset('conversionUnitId', 'conversionFactor');
        $this->resetErrorBag(['conversionUnitId', 'conversionFactor']);
    }

    public function removeConversion(int $index): void
    {
        $row = $this->conversionRows[$index] ?? null;

        if ($row && (int) $row['id'] > 0) {
            ItemUnitConversion::whereKey($row['id'])->delete();
        }

        unset($this->conversionRows[$index]);
        $this->conversionRows = array_values($this->conversionRows);
    }

    public function addBomComponent(): void
    {
        $this->validate([
            'bomComponentId' => ['required', 'exists:items,id'],
            'bomQuantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($this->itemId && (int) $this->bomComponentId === $this->itemId) {
            $this->addError('bomComponentId', __('Komponen tidak boleh sama dengan barang kit.'));

            return;
        }

        $this->bomRows[] = [
            'id' => 0,
            'component_item_id' => (string) $this->bomComponentId,
            'quantity' => (string) $this->bomQuantity,
        ];

        $this->reset('bomComponentId', 'bomQuantity');
        $this->bomQuantity = '1';
        $this->resetErrorBag(['bomComponentId', 'bomQuantity']);
    }

    public function removeBomComponent(int $index): void
    {
        $row = $this->bomRows[$index] ?? null;

        if ($row && (int) $row['id'] > 0) {
            ItemBom::whereKey($row['id'])->delete();
        }

        unset($this->bomRows[$index]);
        $this->bomRows = array_values($this->bomRows);
    }

    public function save()
    {
        $this->barcode = trim($this->barcode);
        $this->brand = trim($this->brand);
        $this->description = trim($this->description);
        $this->primary_supplier_id = trim($this->primary_supplier_id);
        $this->consignor_id = trim($this->consignor_id);

        $data = $this->validate();

        unset($data['image']);

        $data['barcode'] = $data['barcode'] !== '' ? $data['barcode'] : null;
        $data['brand'] = $data['brand'] !== '' ? $data['brand'] : null;
        $data['description'] = $data['description'] !== '' ? $data['description'] : null;
        $data['primary_supplier_id'] = $data['primary_supplier_id'] !== '' ? $data['primary_supplier_id'] : null;
        $data['consignor_id'] = ($data['consignor_id'] ?? '') !== '' && $data['ownership'] === 'consignment' ? $data['consignor_id'] : null;
        $data['cost'] = $data['cost'] ?? 0;
        $data['price'] = $data['price'] ?? 0;

        $images = app(ImageService::class);

        if ($this->itemId) {
            $item = Item::findOrFail($this->itemId);

            $this->authorize('update', $item);

            $old = $item->toArray();

            if ($this->image) {
                $images->delete($item->image_path);
                $data['image_path'] = $images->store($this->image, 'items');
            } elseif ($this->removeImage) {
                $images->delete($item->image_path);
                $data['image_path'] = null;
            }

            $data['updated_by'] = auth()->id();
            $item->update($data);

            AuditLogger::logModel('update', $item, $old, $item->fresh()->toArray());
        } else {
            $this->authorize('create', Item::class);

            if ($this->image) {
                $data['image_path'] = $images->store($this->image, 'items');
            }

            $data['created_by'] = auth()->id();
            $item = Item::create($data);

            AuditLogger::logModel('create', $item, null, $item->toArray());
        }

        if ($item->primary_supplier_id && (float) ($item->cost ?? 0) > 0) {
            SupplierItemPrice::updateOrCreate(
                ['supplier_id' => $item->primary_supplier_id, 'item_id' => $item->id],
                ['price' => (float) $item->cost, 'lead_time_days' => 7]
            );
        }

        foreach ($this->conversionRows as $row) {
            $factor = (float) $row['factor'];
            $unitId = (int) $row['unit_id'];

            if ($factor <= 0 || $unitId <= 0 || $unitId === (int) $item->unit_id) {
                continue;
            }

            ItemUnitConversion::updateOrCreate(
                ['item_id' => $item->id, 'unit_id' => $unitId],
                ['factor' => $factor],
            );
        }

        foreach ($this->bomRows as $row) {
            $componentId = (int) $row['component_item_id'];
            $quantity = (int) $row['quantity'];

            if ($componentId <= 0 || $quantity <= 0 || $componentId === $item->id) {
                continue;
            }

            ItemBom::updateOrCreate(
                ['kit_item_id' => $item->id, 'component_item_id' => $componentId],
                ['quantity' => $quantity, 'created_by' => auth()->id()],
            );
        }

        $this->dispatch('toast', type: 'success', message: __('Tersimpan'));

        return $this->redirect(route('items.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.master-data.item-form', [
            'baseUnitId' => $this->itemId ? (Item::whereKey($this->itemId)->value('unit_id') ?? '') : $this->unit_id,
            'categories' => Category::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'code']),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'componentItems' => Item::where('status', 'active')
                ->when($this->itemId, fn ($q) => $q->whereKeyNot($this->itemId))
                ->orderBy('name')
                ->get(['id', 'sku', 'name']),
        ]);
    }
}
