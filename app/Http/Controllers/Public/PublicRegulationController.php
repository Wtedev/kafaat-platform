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

        return view('public.regulations', compact('regulations'));
    }
}
