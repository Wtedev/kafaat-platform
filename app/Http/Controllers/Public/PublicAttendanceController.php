<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ProgramAttendanceLink;
use App\Services\Attendance\ProgramAttendanceLinkService;
use App\Services\Surveys\ProgramSurveyService;
use App\Services\Surveys\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicAttendanceController extends Controller
{
    public function __construct(
        private readonly ProgramAttendanceLinkService $attendance,
        private readonly TurnstileVerifier $turnstile,
    ) {}

    public function show(string $token): View
    {
        $link = $this->find($token);
        if (! $link->isOpen()) {
            return $this->unavailable($link);
        }

        $match = session($this->matchKey($token));
        if (is_array($match) && isset($match['short_name'], $match['registration_id'])) {
            return view('public.attendance.check-in', [
                'step' => 'confirm',
                'link' => $link,
                'shortName' => (string) $match['short_name'],
                'attendedAt' => null,
                'turnstileSiteKey' => null,
            ]);
        }

        return view('public.attendance.check-in', [
            'step' => 'identify',
            'link' => $link,
            'shortName' => null,
            'attendedAt' => null,
            'turnstileSiteKey' => $this->turnstile->siteKey(),
        ]);
    }

    public function identify(Request $request, string $token): View|RedirectResponse
    {
        $link = $this->find($token);
        if (! $link->isOpen()) {
            return $this->unavailable($link);
        }

        $nationalId = (string) $request->input('national_id', '');
        $request->request->remove('national_id');
        $turnstileToken = (string) ($request->input('turnstile_token') ?: $request->input('cf-turnstile-response', ''));

        if (! $this->turnstile->passes($turnstileToken, $request->ip())) {
            return redirect()
                ->route('public.attendance.show', $token)
                ->withErrors(['turnstile_token' => ProgramSurveyService::TURNSTILE_MESSAGE]);
        }

        $result = $this->attendance->identify($link, $nationalId);

        if ($result['status'] === 'closed') {
            return $this->unavailable($link);
        }

        if ($result['status'] === 'missing') {
            return redirect()
                ->route('public.attendance.show', $token)
                ->withErrors(['national_id' => ProgramAttendanceLinkService::NOT_FOUND_MESSAGE]);
        }

        if ($result['status'] === 'already') {
            return view('public.attendance.check-in', [
                'step' => 'already',
                'link' => $link,
                'shortName' => null,
                'attendedAt' => $result['attended_at'],
                'turnstileSiteKey' => null,
            ]);
        }

        session()->put($this->matchKey($token), [
            'registration_id' => $result['registration_id'],
            'short_name' => $result['short_name'],
        ]);

        return redirect()->route('public.attendance.show', $token);
    }

    public function confirm(Request $request, string $token): View|RedirectResponse
    {
        $link = $this->find($token);
        $match = session($this->matchKey($token));
        session()->forget($this->matchKey($token));

        if (! is_array($match) || ! isset($match['registration_id'])) {
            return redirect()->route('public.attendance.show', $token);
        }

        try {
            $mark = $this->attendance->confirm($link, (int) $match['registration_id'], $request->ip());
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            if ($message === ProgramAttendanceLinkService::ALREADY_PUBLIC_MESSAGE) {
                return view('public.attendance.check-in', [
                    'step' => 'already',
                    'link' => $link,
                    'shortName' => null,
                    'attendedAt' => null,
                    'turnstileSiteKey' => null,
                ]);
            }

            if ($message === ProgramAttendanceLinkService::UNAVAILABLE_MESSAGE || $message === ProgramAttendanceLinkService::CANCELLED_MESSAGE) {
                return $this->unavailable($link);
            }

            return redirect()
                ->route('public.attendance.show', $token)
                ->withErrors(['national_id' => ProgramAttendanceLinkService::NOT_FOUND_MESSAGE]);
        }

        return view('public.attendance.check-in', [
            'step' => 'done',
            'link' => $link,
            'shortName' => null,
            'attendedAt' => $mark->riyadhLabel(),
            'turnstileSiteKey' => null,
        ]);
    }

    private function find(string $token): ProgramAttendanceLink
    {
        return ProgramAttendanceLink::query()->with('program')->where('token', $token)->firstOrFail();
    }

    private function unavailable(ProgramAttendanceLink $link): View
    {
        return view('public.attendance.check-in', [
            'step' => 'unavailable',
            'link' => $link,
            'shortName' => null,
            'attendedAt' => null,
            'turnstileSiteKey' => null,
        ]);
    }

    private function matchKey(string $token): string
    {
        return 'attendance_match.'.$token;
    }
}
