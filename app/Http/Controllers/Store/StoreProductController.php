<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Mail\ConsultPriceLeadMail;
use App\Support\ProductUrl;
use App\Services\Catalog\CatalogProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class StoreProductController extends Controller
{
    public function show(Request $request, CatalogProvider $catalog, string $idOrReference)
    {
        try {
            $product = $this->resolveProduct($catalog, $idOrReference);
            $headerCategories = $catalog->categories();
        } catch (\RuntimeException $e) {
            return response()
                ->view('store.error', ['message' => $e->getMessage()], 503);
        }

        if (! $product) {
            abort(404);
        }

        $canonicalUrl = ProductUrl::url($product);
        $canonicalPath = parse_url($canonicalUrl, PHP_URL_PATH);
        if (is_string($canonicalPath) && $canonicalPath !== '' && $request->getPathInfo() !== $canonicalPath) {
            return redirect()->to($canonicalUrl, 301);
        }

        $title = trim((string) ($product['title'] ?? 'Produto'));
        $make = trim((string) ($product['make_name'] ?? $product['category'] ?? ''));
        $model = trim((string) ($product['model_name'] ?? ''));
        $reference = trim((string) ($product['reference'] ?? ''));
        $additionalReferences = array_values(array_filter(
            (array) ($product['additional_references'] ?? []),
            fn ($ref): bool => is_scalar($ref) && trim((string) $ref) !== ''
        ));
        $descriptionParts = array_values(array_filter([
            $title,
            $make !== '' ? 'Marca: '.$make : null,
            $model !== '' ? 'Modelo: '.$model : null,
            $reference !== '' ? 'Ref: '.$reference : null,
            count($additionalReferences) > 0 ? 'Outras refs: '.implode(', ', array_slice($additionalReferences, 0, 4)) : null,
        ]));
        $description = implode(' | ', $descriptionParts);

        $metaImage = (string) ($product['cover_image'] ?? '');
        if ($metaImage === '') {
            $images = $product['images'] ?? [];
            if (is_array($images) && isset($images[0]) && is_string($images[0])) {
                $metaImage = $images[0];
            }
        }
        if ($metaImage === '') {
            $metaImage = asset('assets/img/logo.png');
        }

        return view('store.product', [
            'product' => $product,
            'headerCategories' => $headerCategories ?? [],
            'metaTitle' => $title,
            'metaDescription' => $description,
            'metaCanonical' => $canonicalUrl,
            'metaImage' => $metaImage,
            'metaType' => 'product',
            'metaRobots' => 'index,follow',
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
            $product = $this->resolveProduct($catalog, $idOrReference);
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
            'product_url' => ProductUrl::url($product),
        ]));

        return back()->with('success', 'Pedido enviado com sucesso. Vamos entrar em contacto brevemente.');
    }

    private function resolveProduct(CatalogProvider $catalog, string $segment): ?array
    {
        foreach (ProductUrl::lookupCandidatesFromSegment($segment) as $candidate) {
            $product = $catalog->product($candidate);

            if (is_array($product)) {
                return $product;
            }
        }

        return null;
    }
}
