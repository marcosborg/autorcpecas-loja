<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\SibsWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SibsWebhookController extends Controller
{
    public function __invoke(Request $request, SibsWebhookService $service): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        $providedSecret = (string) (
            $request->header('X-Webhook-Secret')
            ?? $request->input('webhook_secret')
            ?? $request->query('token')
            ?? ''
        );

        $result = $service->handle($payload, $providedSecret);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'order_id' => $result['order_id'] ?? null,
        ], (int) $result['status']);
    }
}

