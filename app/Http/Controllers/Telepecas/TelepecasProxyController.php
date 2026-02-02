<?php

namespace App\Http\Controllers\Telepecas;

use App\Http\Controllers\Controller;
use App\Services\Telepecas\TelepecasClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelepecasProxyController extends Controller
{
    public function __construct(private readonly TelepecasClient $client)
    {
    }

    public function call(Request $request, string $endpoint): JsonResponse
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '' || str_contains($endpoint, '..')) {
            return response()->json([
                'ok' => false,
                'error' => 'Endpoint inválido.',
            ], 400);
        }

        $allowed = (array) config('telepecas.allowed_endpoints', []);

        if (count($allowed) === 0) {
            return response()->json([
                'ok' => false,
                'error' => 'Proxy TelePeças desativado: define TELEPECAS_ALLOWED_ENDPOINTS no .env.',
            ], 503);
        }

        if (! in_array($endpoint, $allowed, true)) {
            return response()->json([
                'ok' => false,
                'error' => 'Endpoint não permitido.',
            ], 403);
        }

        $payload = $request->json()->all();

        if (! is_array($payload)) {
            return response()->json([
                'ok' => false,
                'error' => 'Body JSON inválido.',
            ], 400);
        }

        $useCache = $request->boolean('cache', true);
        $ttl = $request->integer('ttl');
        $ttl = is_int($ttl) && $ttl >= 0 ? min($ttl, 3600) : null;

        try {
            $result = $this->client->post($endpoint, $payload, $ttl, $useCache);
        } catch (\RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }

        $upstreamStatus = $result['status'] ?? null;
        $status = 502;

        if ($result['ok'] ?? false) {
            $status = 200;
        } elseif (is_int($upstreamStatus) && $upstreamStatus >= 400 && $upstreamStatus <= 499) {
            $status = $upstreamStatus;
        }

        return response()->json($result, $status);
    }
}
