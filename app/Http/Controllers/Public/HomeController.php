<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Partner;
use App\Support\CompetencyTrackCatalog;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $news = News::published()
            ->latest('published_at')
            ->take(9)
            ->get();

        $partners = Partner::active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('public.home', [
            'news' => $news,
            'partners' => $partners,
            'programCounts' => CompetencyTrackCatalog::publishedProgramCounts(),
            'trackPrograms' => CompetencyTrackCatalog::featuredProgramsByTrack(5),
            // Static Year of Impact 2026 art in public/images/home (git-tracked for Railway).
            'heroImageUrl' => asset('images/home/hero-year-of-impact.jpg'),
            'heroImageMobileUrl' => asset('images/home/hero-year-of-impact-mobile.jpg'),
        ]);
    }
}
