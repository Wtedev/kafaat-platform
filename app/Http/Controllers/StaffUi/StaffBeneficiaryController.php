<?php

namespace App\Http\Controllers\StaffUi;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StaffUi\StaffBeneficiaryIndex;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffBeneficiaryController extends Controller
{
    public function index(Request $request, StaffBeneficiaryIndex $index): View
    {
        $this->authorize('viewAny', User::class);

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');
        $completeness = (string) $request->query('profile', '');

        if (! in_array($status, ['', 'active', 'inactive'], true)) {
            $status = '';
        }

        if (! in_array($completeness, ['', 'complete', 'incomplete'], true)) {
            $completeness = '';
        }

        $user = $request->user();

        return view('staff-ui.users.beneficiaries', [
            'staffName' => $user->name,
            'staffEmail' => $user->email,
            'beneficiaries' => $index->paginate($search, $status, $completeness, (int) $request->query('page', 1)),
            'search' => $search,
            'status' => $status,
            'completeness' => $completeness,
            'canViewContact' => $user->can('beneficiaries.view_contact'),
        ]);
    }
}
