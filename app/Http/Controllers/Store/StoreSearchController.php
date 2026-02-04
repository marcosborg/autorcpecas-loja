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
        ]);
    }
}
