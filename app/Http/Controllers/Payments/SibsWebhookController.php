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
        $providedSecret = (string) (
            $request->header('X-Webhook-Secret')
            ?? $request->input('webhook_secret')
            ?? $request->query('token')
            ?? ''
        );

        /** @var array<string, mixed> $headers */
        $headers = $request->headers->all();
        /** @var array<string, mixed> $fallbackPayload */
        $fallbackPayload = $request->all();

        $result = $service->handleIncoming(
            (string) $request->getContent(),
            $headers,
            $fallbackPayload,
            $providedSecret,
        );

        return response()->json($result['response'], (int) $result['status']);
    }
}
