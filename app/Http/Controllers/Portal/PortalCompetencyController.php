<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\CompetencyProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalCompetencyController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = CompetencyProfilePresenter::make($request->user());

        return view('portal.competency', $data);
    }
}
