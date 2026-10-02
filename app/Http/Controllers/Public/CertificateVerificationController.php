<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;

class CertificateVerificationController extends Controller
{
    public function __invoke(Request $request, string $code)
    {
        $certificate = Certificate::query()
            ->with(['user', 'certificateable'])
            ->where('verification_code', $code)
            ->first();

        $snapshot = is_array($certificate?->data_snapshot) ? $certificate->data_snapshot : [];
        $display = [
            'found' => $certificate !== null,
            'revoked' => $certificate?->revoked_at !== null,
            'name' => $this->snapshotValue($snapshot, 'recipient_name') ?? $certificate?->user?->certificateName(),
            'activity' => $this->snapshotValue($snapshot, 'activity_title') ?? $certificate?->certificateable?->title,
            'issuedAt' => $this->snapshotValue($snapshot, 'issue_date_gregorian') ?? $certificate?->issued_at?->timezone(config('app.timezone'))->format('Y/m/d'),
            'number' => $certificate?->certificate_number,
            'status' => $certificate?->revoked_at !== null ? 'ملغاة' : 'صالحة',
        ];

        return view('public.certificate-verify', [
            'certificate' => $certificate,
            'display' => $display,
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function snapshotValue(array $snapshot, string $key): ?string
    {
        $value = $snapshot[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
