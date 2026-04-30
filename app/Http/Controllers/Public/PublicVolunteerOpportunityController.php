<?php

namespace App\Http\Controllers\Public;

use App\Exceptions\DuplicateRegistrationException;
use App\Exceptions\OpportunityCapacityExceededException;
use App\Exceptions\OpportunityNotPublishedException;
use App\Http\Controllers\Controller;
use App\Models\VolunteerOpportunity;
use App\Services\VolunteerRegistrationService;
use Illuminate\Http\Request;

class PublicVolunteerOpportunityController extends Controller
{
    public function __construct(
        private readonly VolunteerRegistrationService $registrationService,
    ) {}

    public function index()
    {
        $opportunities = VolunteerOpportunity::published()
            ->latest('published_at')
            ->paginate(12);

        return view('public.volunteering.index', compact('opportunities'));
    }

    public function show(VolunteerOpportunity $volunteerOpportunity)
    {
        abort_if($volunteerOpportunity->status->value !== 'published', 404);

        $userRegistration = null;
        if (auth()->check()) {
            $userRegistration = $volunteerOpportunity->registrations()
                ->where('user_id', auth()->id())
                ->latest()
                ->first();
        }

        return view('public.volunteering.show', compact('volunteerOpportunity', 'userRegistration'));
    }

    public function register(Request $request, VolunteerOpportunity $volunteerOpportunity)
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if ($request->user()->role_type !== 'beneficiary') {
            abort(403);
        }

        try {
            $this->registrationService->register($request->user(), $volunteerOpportunity);
            return back()->with('success', 'تم تسجيلك بنجاح! سيتم مراجعة طلبك قريباً.');
        } catch (DuplicateRegistrationException) {
            return back()->with('error', 'لديك تسجيل نشط بالفعل في هذه الفرصة التطوعية.');
        } catch (OpportunityNotPublishedException) {
            return back()->with('error', 'هذه الفرصة التطوعية غير متاحة للتسجيل حالياً.');
        } catch (OpportunityCapacityExceededException) {
            return back()->with('error', 'الفرصة التطوعية وصلت إلى الحد الأقصى للمتطوعين.');
        }
    }
}
