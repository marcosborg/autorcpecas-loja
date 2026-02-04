<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Database\QueryException;

class PageController extends Controller
{
    public function about()
    {
        return view('pages.about');
    }

    public function contacts()
    {
        return view('pages.contacts');
    }

    public function brands()
    {
        try {
            $brands = Brand::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } catch (QueryException) {
            $brands = collect();
        }

        return view('pages.brands', [
            'brands' => $brands,
        ]);
    }
}

