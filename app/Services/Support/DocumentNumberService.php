<?php

namespace App\Services\Support;

use App\Models\DocumentSequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public static function generate(string $prefix): string
    {
        $period = now()->format('Ymd');

        return DB::transaction(function () use ($prefix, $period): string {
            $sequence = DocumentSequence::where('prefix', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                try {
                    $sequence = DocumentSequence::create([
                        'prefix' => $prefix,
                        'period' => $period,
                        'last_number' => 1,
                    ]);
                } catch (QueryException) {
                    $sequence = DocumentSequence::where('prefix', $prefix)
                        ->where('period', $period)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $sequence->increment('last_number');
                    $sequence->refresh();
                }
            } else {
                $sequence->increment('last_number');
                $sequence->refresh();
            }

            return sprintf('%s-%s-%04d', $prefix, $period, (int) $sequence->last_number);
        });
    }
}
