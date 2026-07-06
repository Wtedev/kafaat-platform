<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Regulation;

class PublicRegulationController extends Controller
{
    public function __invoke()
    {
        $regulations = Regulation::active()
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->groupBy('category');

        $categoryOrder = config('regulations.category_order', []);
        $regulations = $regulations->sortBy(function ($items, $category) use ($categoryOrder) {
            $index = array_search($category, $categoryOrder, true);

            return $index === false ? 999 : $index;
        });

        return view('public.regulations', compact('regulations'));
    }
}
