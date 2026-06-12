<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Catalog\AdvancedCatalogProvider;
use App\Services\Catalog\CatalogSearchCriteria;
use App\Support\ProductUrl;
use App\Services\Catalog\CatalogProvider;
use Illuminate\Http\Request;

class StoreSearchController extends Controller
{
    public function index(Request $request, CatalogProvider $catalog)
    {
        $criteria = CatalogSearchCriteria::fromArray($request->query());

        try {
            $search = $catalog instanceof AdvancedCatalogProvider
                ? $catalog->searchAdvanced($criteria)
                : new \App\Services\Catalog\CatalogSearchResult($catalog->search($criteria->query, $criteria->page, $criteria->perPage));
            $results = $search->paginator;
            $headerCategories = $catalog->categories();
        } catch (\RuntimeException $e) {
            return response()
                ->view('store.error', ['message' => $e->getMessage()], 503);
        }

        return view('store.search', [
            'q' => $criteria->query,
            'criteria' => $criteria,
            'searchFacets' => $search->facets,
            'correctedQuery' => $search->correctedQuery,
            'usedFuzzyFallback' => $search->usedFuzzyFallback,
            'results' => $results,
            'headerCategories' => $headerCategories ?? [],
            'metaTitle' => $criteria->query !== '' ? ('Pesquisa: '.$criteria->query) : 'Pesquisa de pecas',
            'metaDescription' => $criteria->query !== ''
                ? ('Resultados de pesquisa para "'.$criteria->query.'" na loja Auto RC Pecas.')
                : 'Pesquise por referencia, marca ou nome da peca na loja Auto RC Pecas.',
            'metaCanonical' => url('/loja/pesquisa'),
            'metaRobots' => 'noindex,follow',
        ]);
    }

    public function suggestions(Request $request, CatalogProvider $catalog)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 3) {
            return response()->json(['items' => []]);
        }

        try {
            $results = $catalog instanceof AdvancedCatalogProvider
                ? $catalog->searchAdvanced(new CatalogSearchCriteria(query: $q, perPage: 8, includeFacets: false))->paginator
                : $catalog->search($q, 1, 8);
        } catch (\RuntimeException $e) {
            return response()->json(['items' => []], 503);
        }

        $items = collect($results->items())
            ->map(function (array $product) {
                $reference = (string) ($product['reference'] ?? '');
                $productUrl = ProductUrl::url($product);

                return [
                    'title' => (string) ($product['title'] ?? 'Produto'),
                    'reference' => $reference,
                    'make' => (string) ($product['make_name'] ?? ''),
                    'model' => (string) ($product['model_name'] ?? ''),
                    'url' => $productUrl,
                ];
            })
            ->filter(fn (array $item) => $item['url'] !== '')
            ->values()
            ->all();

        return response()->json(['items' => $items]);
    }

    public function filters(Request $request, CatalogProvider $catalog)
    {
        if (! $catalog instanceof AdvancedCatalogProvider) {
            return response()->json(['makes' => $catalog->categories(), 'models' => [], 'pieces' => []]);
        }

        try {
            $result = $catalog->searchAdvanced(new CatalogSearchCriteria(
                make: trim((string) $request->query('make', '')),
                perPage: 1,
            ));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($result->facets);
    }
}
