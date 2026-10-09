<?php

namespace App\Http\Controllers\Api;

use App\Models\Currency;
use App\Services\Support\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends ApiController
{
    public function index(): JsonResponse
    {
        $base = CurrencyService::baseCode();

        $rows = Currency::active()->map(fn ($currency) => [
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'is_base' => (bool) $currency->is_base,
            'rate_to_base' => CurrencyService::rate($currency->code),
        ]);

        return $this->ok(['base' => $base, 'currencies' => $rows->values()->all()], 'OK');
    }

    public function convert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'from' => ['required', 'string', 'max:10'],
            'to' => ['required', 'string', 'max:10'],
            'date' => ['nullable', 'date'],
        ]);

        return $this->ok([
            'amount' => (float) $data['amount'],
            'from' => $data['from'],
            'to' => $data['to'],
            'converted' => CurrencyService::convert((float) $data['amount'], $data['from'], $data['to'], $data['date'] ?? null),
        ], 'OK');
    }
}
