<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('idempotency.enabled', true)) {
            return $next($request);
        }

        $headerName = (string) config('idempotency.header', 'Idempotency-Key');
        $key = (string) $request->headers->get($headerName, '');

        if (trim($key) === '') {
            return $next($request);
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $userId = $request->user()?->id;
        $path = '/'.ltrim($request->path(), '/');
        $method = $request->method();
        $hash = hash('sha256', $method.'|'.$path.'|'.$request->getContent());

        try {
            $row = IdempotencyKey::where('user_id', $userId)->where('key', $key)->first();
        } catch (\Throwable) {
            return $next($request);
        }

        if ($row !== null) {
            if ($row->status_code !== null) {
                if ($row->request_hash !== $hash || $row->method !== $method || $row->path !== $path) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Idempotency-Key reused with a different request.',
                    ], 409);
                }

                $headers = is_array($row->response_headers) ? $row->response_headers : [];
                $response = response()->json(json_decode((string) $row->response_body, true), (int) $row->status_code);
                $response->headers->set('Idempotent-Replay', 'true');

                foreach ($headers as $name => $value) {
                    if (is_string($name) && is_string($value)) {
                        $response->headers->set($name, $value);
                    }
                }

                return $response;
            }

            if ($row->locked_at !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'A request with this Idempotency-Key is already in progress.',
                ], 409);
            }
        }

        if ($row === null) {
            try {
                $row = IdempotencyKey::create([
                    'key' => $key,
                    'user_id' => $userId,
                    'method' => $method,
                    'path' => $path,
                    'request_hash' => $hash,
                    'locked_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $existing = IdempotencyKey::where('user_id', $userId)->where('key', $key)->first();

                if ($existing !== null && $existing->locked_at !== null && $existing->status_code === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A request with this Idempotency-Key is already in progress.',
                    ], 409);
                }

                if ($existing !== null) {
                    return $this->handle($request, $next);
                }
            }
        } else {
            $row->forceFill(['locked_at' => now()])->save();
        }

        /** @var Response $response */
        $response = $next($request);

        $this->storeResponse($row, $response, $hash, $method, $path);

        return $response;
    }

    private function storeResponse(IdempotencyKey $row, Response $response, string $hash, string $method, string $path): void
    {
        $status = $response->getStatusCode();

        if ($status >= 500) {
            try {
                $row->forceFill(['locked_at' => null])->save();
            } catch (\Throwable) {
            }

            return;
        }

        $body = method_exists($response, 'getContent') ? (string) $response->getContent() : '';

        try {
            DB::transaction(function () use ($row, $status, $body, $hash, $method, $path): void {
                $fresh = IdempotencyKey::find($row->id);

                if ($fresh === null) {
                    return;
                }

                $fresh->forceFill([
                    'request_hash' => $hash,
                    'method' => $method,
                    'path' => $path,
                    'status_code' => $status,
                    'response_body' => $body,
                    'locked_at' => null,
                ])->save();
            });
        } catch (\Throwable) {
        }
    }
}
