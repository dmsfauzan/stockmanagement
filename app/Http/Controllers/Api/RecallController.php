<?php

namespace App\Http\Controllers\Api;

use App\Models\BatchRecall;
use App\Services\Inventory\RecallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecallController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = BatchRecall::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->string('status')))
            ->orderByDesc('recalled_at');

        $paginator = $query->paginate(min(100, max(1, (int) $request->integer('per_page', 15))))->withQueryString();

        $paginator->getCollection()->transform(fn ($r) => [
            'id' => $r->id,
            'type' => $r->type,
            'batch_number' => $r->batch_number,
            'serial_number' => $r->serial_number,
            'reason' => $r->reason,
            'status' => $r->status,
            'recalled_at' => $r->recalled_at?->toIso8601String(),
        ]);

        return $this->paginated($paginator);
    }

    public function impact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:batch,serial'],
            'value' => ['required', 'string', 'max:80'],
        ]);

        $impact = RecallService::impact($data['type'], $data['value']);

        return $this->ok([
            'type' => $impact['type'],
            'value' => $impact['value'],
            'on_hand' => $impact['on_hand'],
            'stock' => $impact['stock']->values()->all(),
            'documents' => $impact['documents']->values()->all(),
            'active_recall' => $impact['active_recall']?->only(['id', 'status']),
        ], 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:batch,serial'],
            'value' => ['required', 'string', 'max:80'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $recall = RecallService::recall($data['type'], $data['value'], $data['reason']);

        return $this->created(['id' => $recall->id, 'status' => $recall->status], 'Batch ditandai ditarik.');
    }

    public function lift(int $id): JsonResponse
    {
        $recall = BatchRecall::findOrFail($id);
        RecallService::lift($recall);

        return $this->ok(null, 'OK');
    }
}
