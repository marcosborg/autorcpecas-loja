<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Brand;
use App\Services\Catalog\CatalogProvider;
use Illuminate\Database\QueryException;

class HomeController extends Controller
{
    public function index(CatalogProvider $catalog)
    {
        try {
            $banner = Banner::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->first();
        } catch (QueryException) {
            $banner = null;
        }

        try {
            $brands = Brand::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } catch (QueryException) {
            $brands = collect();
        }

        try {
            $products = $catalog->randomProducts(16);
        } catch (\RuntimeException $e) {
            $products = [];
        }

        try {
            $categories = $catalog->categories();
        } catch (\RuntimeException $e) {
            $categories = [];
        }

        return view('home', [
            'banner' => $banner,
            'brands' => $brands,
            'products' => $products,
            'headerCategories' => $categories,
        ]);
    }
}
