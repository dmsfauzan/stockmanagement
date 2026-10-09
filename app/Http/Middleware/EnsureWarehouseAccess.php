<?php

namespace App\Http\Middleware;

use App\Models\Warehouse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWarehouseAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $sessionWarehouse = session('active_warehouse_id');

        if ($sessionWarehouse !== null && $sessionWarehouse !== '' && ! $user->canAccessWarehouse((int) $sessionWarehouse)) {
            session()->forget('active_warehouse_id');
        }

        $bound = $request->route('warehouse');

        if ($bound instanceof Warehouse && ! $user->canAccessWarehouse((int) $bound->id)) {
            abort(403, __('Anda tidak memiliki akses ke gudang ini.'));
        }

        return $next($request);
    }
}
