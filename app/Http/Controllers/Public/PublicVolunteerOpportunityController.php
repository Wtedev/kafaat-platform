<?php

namespace App\Http\Controllers\Public;

use App\Enums\OpportunityStatus;
use App\Http\Controllers\Controller;
use App\Models\VolunteerOpportunity;
use Illuminate\View\View;

/**
 * Volunteer opportunities are listed here; registration happens on the national
 * volunteering platform through each opportunity's link.
 */
class PublicVolunteerOpportunityController extends Controller
{
    public function index(): View
    {
        $opportunities = VolunteerOpportunity::published()
            ->latest('published_at')
            ->paginate(12);

        return view('public.volunteering.index', compact('opportunities'));
    }

    public function show(VolunteerOpportunity $volunteerOpportunity): View
    {
        abort_unless($volunteerOpportunity->status === OpportunityStatus::Published, 404);

        return view('public.volunteering.show', compact('volunteerOpportunity'));
    }
}
