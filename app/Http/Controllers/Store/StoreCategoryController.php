<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Catalog\CatalogProvider;
use Illuminate\Http\Request;

class StoreCategoryController extends Controller
{
    public function index(CatalogProvider $catalog)
    {
        try {
            $categories = $catalog->categories();
            $totalProducts = $catalog->totalProducts();
            $totalBreakdown = $catalog->totalProductsBreakdown();
        } catch (\RuntimeException $e) {
            return response()
                ->view('store.error', ['message' => $e->getMessage()], 503);
        }

        return view('store.browse', [
            'categories' => $categories,
            'totalProducts' => $totalProducts,
            'totalBreakdown' => $totalBreakdown,
            'selectedCategorySlug' => null,
            'models' => [],
            'products' => null,
            'categoryName' => null,
            'modelName' => null,
            'facets' => [],
            'selectedModel' => null,
            'selectedPiece' => null,
            'selectedState' => null,
            'selectedCondition' => null,
            'selectedPrice' => null,
        ]);
    }

    public function show(Request $request, CatalogProvider $catalog, string $slug)
    {
        try {
            $categories = $catalog->categories();
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('perPage', 24);
            $data = $catalog->productsByCategory($slug, $page, $perPage);
            $models = [];

            $models = $catalog->modelsForMakeSlug($slug);
            $modelName = null;

            $selectedModel = (string) $request->query('model', '');
            if ($selectedModel !== '') {
                foreach ($models as $m) {
                    if (($m['slug'] ?? '') === $selectedModel) {
                        $modelName = (string) ($m['name'] ?? $selectedModel);
                        break;
                    }
                }
            }
        } catch (\RuntimeException $e) {
            return response()
                ->view('store.error', ['message' => $e->getMessage()], 503);
        }

        return view('store.browse', [
            'categories' => $categories,
            'selectedCategorySlug' => $slug,
            'categoryName' => $data['categoryName'],
            'modelName' => $modelName,
            'products' => $data['paginator'],
            'models' => $models,
            'selectedModel' => (string) $request->query('model', ''),
            'selectedPiece' => (string) $request->query('piece', ''),
            'selectedState' => (string) $request->query('state', ''),
            'selectedCondition' => (string) $request->query('condition', ''),
            'selectedPrice' => (string) $request->query('price', ''),
            'facets' => (array) ($data['meta']['facets'] ?? []),
            'totalProducts' => $catalog->totalProducts(),
        ]);
    }
}
