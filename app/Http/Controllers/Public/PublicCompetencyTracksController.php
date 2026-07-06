<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\CompetencyTrackCatalog;

class PublicCompetencyTracksController extends Controller
{
    public function __invoke()
    {
        return view('public.tracks.index', [
            'programCounts' => CompetencyTrackCatalog::publishedProgramCounts(),
            'pathCounts' => CompetencyTrackCatalog::publishedPathCounts(),
        ]);
    }
}
