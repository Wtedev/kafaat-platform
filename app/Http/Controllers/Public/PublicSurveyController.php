<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ProgramSurvey;
use App\Services\Surveys\ProgramSurveyService;
use App\Services\Surveys\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicSurveyController extends Controller
{
    public function __construct(
        private readonly ProgramSurveyService $surveys,
        private readonly TurnstileVerifier $turnstile,
    ) {}

    public function show(string $publicToken): View
    {
        $survey = $this->find($publicToken);
        if (! $survey->isOpen()) {
            return $this->unavailable();
        }

        $confirmed = session($this->confirmedKey($publicToken));
        if (is_int($confirmed) || (is_string($confirmed) && ctype_digit($confirmed))) {
            $survey->load('questions');

            return view('public.surveys.show', [
                'step' => 'form',
                'survey' => $survey,
                'turnstileSiteKey' => null,
            ]);
        }

        $match = session($this->matchKey($publicToken));
        if (is_array($match) && isset($match['short_name'], $match['registration_id'])) {
            return view('public.surveys.show', [
                'step' => 'confirm',
                'survey' => $survey,
                'shortName' => (string) $match['short_name'],
                'turnstileSiteKey' => null,
            ]);
        }

        return view('public.surveys.show', [
            'step' => 'identify',
            'survey' => $survey,
            'turnstileSiteKey' => $this->turnstile->siteKey(),
        ]);
    }

    public function identify(Request $request, string $publicToken): View|RedirectResponse
    {
        $survey = $this->find($publicToken);
        if (! $survey->isOpen()) {
            return $this->unavailable();
        }

        $nationalId = (string) $request->input('national_id', '');
        $request->request->remove('national_id');
        $turnstileToken = (string) ($request->input('turnstile_token') ?: $request->input('cf-turnstile-response', ''));

        if (! $this->turnstile->passes($turnstileToken, $request->ip())) {
            return redirect()
                ->route('public.surveys.show', $publicToken)
                ->withErrors(['turnstile_token' => ProgramSurveyService::TURNSTILE_MESSAGE]);
        }

        $result = $this->surveys->identify($survey, $nationalId, $request->ip());

        if ($result['status'] === 'missing') {
            return redirect()
                ->route('public.surveys.show', $publicToken)
                ->withErrors(['national_id' => ProgramSurveyService::NOT_FOUND_MESSAGE]);
        }

        if ($result['status'] === 'completed') {
            return view('public.surveys.show', [
                'step' => 'already',
                'survey' => $survey,
                'turnstileSiteKey' => null,
            ]);
        }

        session()->put($this->matchKey($publicToken), [
            'registration_id' => $result['registration_id'],
            'short_name' => $result['short_name'],
        ]);

        return redirect()->route('public.surveys.show', $publicToken);
    }

    public function confirm(Request $request, string $publicToken): RedirectResponse
    {
        $survey = $this->find($publicToken);
        if (! $survey->isOpen()) {
            return redirect()->route('public.surveys.show', $publicToken);
        }

        $match = session($this->matchKey($publicToken));
        session()->forget($this->matchKey($publicToken));

        if ($request->input('choice') === 'yes' && is_array($match) && isset($match['registration_id'])) {
            session()->put($this->confirmedKey($publicToken), (int) $match['registration_id']);
        }

        return redirect()->route('public.surveys.show', $publicToken);
    }

    public function submit(Request $request, string $publicToken): View|RedirectResponse
    {
        $survey = $this->find($publicToken);
        if (! $survey->isOpen()) {
            return $this->unavailable();
        }

        $registrationId = session($this->confirmedKey($publicToken));
        if (! is_int($registrationId) && ! (is_string($registrationId) && ctype_digit((string) $registrationId))) {
            return redirect()->route('public.surveys.show', $publicToken);
        }

        try {
            $this->surveys->submit($survey, (int) $registrationId, (array) $request->input('answers', []));
        } catch (ValidationException $exception) {
            return redirect()
                ->route('public.surveys.show', $publicToken)
                ->withErrors($exception->errors());
        }

        session()->forget($this->confirmedKey($publicToken));

        return view('public.surveys.show', [
            'step' => 'thanks',
            'survey' => $survey,
            'turnstileSiteKey' => null,
        ]);
    }

    private function find(string $publicToken): ProgramSurvey
    {
        return ProgramSurvey::query()->where('public_token', $publicToken)->firstOrFail();
    }

    private function unavailable(): View
    {
        return view('public.surveys.show', [
            'step' => 'unavailable',
            'survey' => null,
            'turnstileSiteKey' => null,
        ]);
    }

    private function matchKey(string $publicToken): string
    {
        return 'survey_match.'.$publicToken;
    }

    private function confirmedKey(string $publicToken): string
    {
        return 'survey_registration.'.$publicToken;
    }
}
