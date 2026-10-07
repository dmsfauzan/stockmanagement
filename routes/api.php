<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'active'])->group(function (): void {
    Route::get('/me', function () {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'roles' => auth()->user()->roleSlugs(),
                'permissions' => auth()->user()->permissionSlugs(),
            ],
        ]);
    })->name('api.me');
});
