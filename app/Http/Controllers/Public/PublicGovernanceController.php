<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BoardMember;
use App\Models\GovernanceDocument;

class PublicGovernanceController extends Controller
{
    public function __invoke()
    {
        $boardMembers = BoardMember::active()
            ->orderBy('sort_order')
            ->get();

        $documents = GovernanceDocument::active()
            ->orderBy('sort_order')
            ->orderByDesc('document_date')
            ->get()
            ->groupBy('type');

        return view('public.governance', compact('boardMembers', 'documents'));
    }
}
