<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BoardMember;
use App\Models\InvestmentDecisionYear;
use App\Models\GovernanceCommittee;
use App\Models\GovernanceDocument;

class PublicGovernanceController extends Controller
{
    public function __invoke()
    {
        $boardMembers = BoardMember::active()
            ->board()
            ->orderBy('sort_order')
            ->get();

        $generalAssemblyMembers = BoardMember::active()
            ->generalAssembly()
            ->orderBy('sort_order')
            ->get();

        $standingCommittees = GovernanceCommittee::active()
            ->with(['activeMembers'])
            ->orderBy('sort_order')
            ->get();

        $investmentDecisionYears = InvestmentDecisionYear::active()
            ->with(['activeItems'])
            ->orderBy('sort_order')
            ->orderByDesc('year')
            ->get();

        $documents = GovernanceDocument::active()
            ->orderBy('sort_order')
            ->orderByDesc('document_date')
            ->get()
            ->groupBy('type');

        $boardTerm = config('governance.board_term');

        return view('public.governance', compact(
            'boardMembers',
            'generalAssemblyMembers',
            'standingCommittees',
            'investmentDecisionYears',
            'documents',
            'boardTerm',
        ));
    }
}
