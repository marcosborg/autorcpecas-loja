<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Catalog\CatalogProvider;
use Illuminate\Http\Request;

class StoreSearchController extends Controller
{
    public function index(Request $request, CatalogProvider $catalog)
    {
        $q = (string) $request->query('q', '');
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 24);

        try {
            $results = $catalog->search($q, $page, $perPage);
            $headerCategories = $catalog->categories();
        } catch (\RuntimeException $e) {
            return response()
                ->view('store.error', ['message' => $e->getMessage()], 503);
        }

        return view('store.search', [
            'q' => $q,
            'results' => $results,
            'headerCategories' => $headerCategories ?? [],
            'metaTitle' => $q !== '' ? ('Pesquisa: '.$q) : 'Pesquisa de pecas',
            'metaDescription' => $q !== ''
                ? ('Resultados de pesquisa para "'.$q.'" na loja Auto RC Pecas.')
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
            $results = $catalog->search($q, 1, 8);
        } catch (\RuntimeException $e) {
            return response()->json(['items' => []], 503);
        }

        $items = collect($results->items())
            ->map(function (array $product) {
                $reference = (string) ($product['reference'] ?? '');
                $productKey = (string) (($product['id'] ?? null) ?: $reference);

                return [
                    'title' => (string) ($product['title'] ?? 'Produto'),
                    'reference' => $reference,
                    'url' => url('/loja/produtos/' . urlencode($productKey)),
                ];
            })
            ->filter(fn (array $item) => $item['url'] !== '')
            ->values()
            ->all();

        return response()->json(['items' => $items]);
    }
}
