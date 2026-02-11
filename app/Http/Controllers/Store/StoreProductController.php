<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Mail\ConsultPriceLeadMail;
use App\Services\Catalog\CatalogProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class StoreProductController extends Controller
{
    public function show(CatalogProvider $catalog, string $idOrReference)
    {
        try {
            $product = $catalog->product($idOrReference);
            $headerCategories = $catalog->categories();
        } catch (\RuntimeException $e) {
            return response()
                ->view('store.error', ['message' => $e->getMessage()], 503);
        }

        if (! $product) {
            abort(404);
        }

        return view('store.product', [
            'product' => $product,
            'headerCategories' => $headerCategories ?? [],
        ]);
    }

    public function requestConsultation(Request $request, CatalogProvider $catalog, string $idOrReference): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:1200'],
            'website' => ['nullable', 'string', 'max:255'],
            'form_started_at' => ['nullable', 'integer'],
        ]);

        // Honeypot: bots often fill hidden fields.
        if ($request->filled('website')) {
            return back()->withErrors(['consult' => 'Pedido inválido.'])->withInput();
        }

        // Time-trap: suspicious if submitted too quickly.
        $formStartedAt = (int) $request->input('form_started_at', 0);
        if ($formStartedAt > 0) {
            $elapsed = now()->timestamp - $formStartedAt;
            if ($elapsed >= 0 && $elapsed < 3) {
                return back()->withErrors(['consult' => 'Pedido inválido.'])->withInput();
            }
        }

        $ip = (string) ($request->ip() ?: 'unknown');
        $email = mb_strtolower(trim((string) $validated['email']));
        $ipKey = 'consult:ip:'.$ip;
        $emailKey = 'consult:email:'.$email;

        if (RateLimiter::tooManyAttempts($ipKey, 10) || RateLimiter::tooManyAttempts($emailKey, 4)) {
            return back()->withErrors(['consult' => 'Demasiados pedidos. Tenta novamente dentro de alguns minutos.'])->withInput();
        }

        RateLimiter::hit($ipKey, 600);
        RateLimiter::hit($emailKey, 600);

        try {
            $product = $catalog->product($idOrReference);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['consult' => $e->getMessage()])->withInput();
        }

        if (! $product) {
            abort(404);
        }

        $to = (string) config('storefront.consult_email', 'marketing@autorcpecas.pt');

        Mail::to($to)->send(new ConsultPriceLeadMail([
            'customer_name' => (string) $validated['name'],
            'customer_email' => (string) $validated['email'],
            'customer_phone' => (string) $validated['phone'],
            'customer_message' => (string) ($validated['message'] ?? ''),
            'product_title' => (string) ($product['title'] ?? 'Produto'),
            'product_reference' => (string) ($product['reference'] ?? ''),
            'product_tp_reference' => (string) ($product['tp_reference'] ?? ''),
            'product_make' => (string) ($product['make_name'] ?? ''),
            'product_model' => (string) ($product['model_name'] ?? ''),
            'product_url' => url('/loja/produtos/'.urlencode($idOrReference)),
        ]));

        return back()->with('success', 'Pedido enviado com sucesso. Vamos entrar em contacto brevemente.');
    }
}
