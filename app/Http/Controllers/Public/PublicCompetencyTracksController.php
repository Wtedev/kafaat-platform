<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;

class PublicCompetencyTracksController extends Controller
{
    public function __invoke()
    {
        return view('public.tracks.index');
    }
}
