<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Models\News;
use App\Models\Partner;
use App\Models\TrainingProgram;
use App\Models\VolunteerOpportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        /** @var Collection<int, array{kind: string, record: LearningPath|TrainingProgram}> */
        $pathsAndPrograms = collect();

        foreach (
            LearningPath::published()
                ->latest('published_at')
                ->get() as $path
        ) {
            $pathsAndPrograms->push(['kind' => 'path', 'record' => $path]);
        }

        foreach (
            TrainingProgram::published()
                ->standaloneCatalog()
                ->latest('published_at')
                ->get() as $program
        ) {
            $pathsAndPrograms->push(['kind' => 'program', 'record' => $program]);
        }

        $pathsAndPrograms = $pathsAndPrograms
            ->sortByDesc(fn (array $row): mixed => $row['record']->published_at)
            ->take(6)
            ->values();

        $opportunities = VolunteerOpportunity::published()
            ->latest('published_at')
            ->take(3)
            ->get();

        $news = News::published()
            ->latest('published_at')
            ->take(3)
            ->get();

        $partners = Partner::active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('public.home', compact('pathsAndPrograms', 'opportunities', 'news', 'partners'));
    }
}
