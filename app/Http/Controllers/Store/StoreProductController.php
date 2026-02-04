<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Catalog\CatalogProvider;

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
}
