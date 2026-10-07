<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'app' => $this->status(true, 'ok'),
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        $failed = collect($checks)->contains(fn ($check) => ! $check['ok']);

        return response()->json([
            'success' => ! $failed,
            'message' => $failed ? 'One or more checks failed' : 'OK',
            'data' => [
                'app' => config('app.name'),
                'environment' => config('app.env'),
                'timestamp' => Carbon::now()->toIso8601String(),
                'checks' => $checks,
            ],
        ], $failed ? 503 : 200);
    }

    public function public(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'app' => config('app.name'),
                'timestamp' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function status(bool $ok, string $detail): array
    {
        return ['ok' => $ok, 'detail' => $detail];
    }

    /** @return array{ok: bool, detail: string} */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $users = DB::table('users')->count();

            return $this->status(true, "connected ({$users} user)");
        } catch (\Throwable $e) {
            return $this->status(false, substr($e->getMessage(), 0, 200));
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function checkCache(): array
    {
        try {
            $key = 'health-'.substr(md5((string) microtime()), 0, 8);
            cache()->put($key, 'ok', 10);

            return cache()->get($key) === 'ok'
                ? $this->status(true, 'read/write ok')
                : $this->status(false, 'unexpected cache value');
        } catch (\Throwable $e) {
            return $this->status(false, substr($e->getMessage(), 0, 200));
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function checkStorage(): array
    {
        try {
            $disk = Setting::get('storage.health_disk', 'local');

            if (! in_array($disk, ['local', 'public'], true)) {
                $disk = 'local';
            }

            $path = 'health/probe-'.uniqid('', true).'.txt';
            Storage::disk($disk)->put($path, 'ok');
            $ok = Storage::disk($disk)->get($path) === 'ok';
            Storage::disk($disk)->delete($path);

            return $ok
                ? $this->status(true, "disk {$disk} writable")
                : $this->status(false, "disk {$disk} mismatch");
        } catch (\Throwable $e) {
            return $this->status(false, substr($e->getMessage(), 0, 200));
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function checkQueue(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            return $this->status(true, "{$pending} pending, {$failed} failed");
        } catch (\Throwable $e) {
            return $this->status(false, substr($e->getMessage(), 0, 200));
        }
    }
}
