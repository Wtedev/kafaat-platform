<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\VolunteerOpportunity;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $paths = LearningPath::published()
            ->latest('published_at')
            ->take(3)
            ->get();

        $programs = TrainingProgram::published()
            ->latest('published_at')
            ->take(3)
            ->get();

        $opportunities = VolunteerOpportunity::published()
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('public.home', compact('paths', 'programs', 'opportunities'));
    }
}
