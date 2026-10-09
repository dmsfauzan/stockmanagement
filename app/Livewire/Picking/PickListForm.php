<?php

namespace App\Livewire\Picking;

use App\Models\GoodsIssue;
use App\Models\User;
use App\Services\Inventory\PickService;
use App\Services\Support\WarehouseAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Buat Pick List')]
class PickListForm extends Component
{
    public string $goods_issue_id = '';

    public string $assigned_to = '';

    public string $notes = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('picking.create'), 403);
    }

    protected function rules(): array
    {
        return [
            'goods_issue_id' => ['required', 'exists:goods_issues,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save()
    {
        $data = $this->validate();

        $issue = GoodsIssue::with('issueItems.location')->findOrFail($data['goods_issue_id']);

        abort_unless(auth()->user()?->canAccessWarehouse((int) $issue->warehouse_id), 403);

        try {
            $pickList = PickService::generateFromIssue(
                $issue,
                $data['assigned_to'] !== '' ? (int) $data['assigned_to'] : null,
                $data['notes'] !== '' ? $data['notes'] : null,
            );
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('Pick list dibuat.'));

        return $this->redirect(route('picking.show', $pickList), navigate: true);
    }

    public function render()
    {
        $issues = GoodsIssue::query()
            ->whereIn('warehouse_id', WarehouseAccess::ids())
            ->whereIn('status', ['approved', 'partial'])
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'number', 'warehouse_id', 'transaction_date']);

        return view('livewire.picking.pick-list-form', [
            'issues' => $issues,
            'users' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
