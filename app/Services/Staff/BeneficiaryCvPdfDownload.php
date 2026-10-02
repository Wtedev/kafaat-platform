<?php

namespace App\Services\Staff;

use App\Models\User;
use App\Services\Portal\CompetencyMpdfExporter;
use App\Services\Portal\CompetencyProfilePresenter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class BeneficiaryCvPdfDownload
{
    public function download(User $user): Response
    {
        abort_unless($user->isPortalUser(), 404);

        $data = CompetencyProfilePresenter::make($user);
        [$filename, $asciiFallback] = $this->filenames($user);

        return (new CompetencyMpdfExporter)->stream($data, $filename, $asciiFallback, 'attachment');
    }

    public function pdfUrl(User $user): string
    {
        return route('admin.beneficiaries.cv-pdf', ['user' => $user]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function filenames(User $user): array
    {
        $name = trim((string) ($user->name ?: 'User'));
        $name = (string) preg_replace('/[\\\\\\/:*?"<>|]+/u', '', $name);
        $name = trim((string) preg_replace('/\\s+/u', ' ', $name));

        return [
            'Kaffah CV '.$name.'.pdf',
            'Kaffah-CV-'.Str::slug(Str::ascii($user->name ?: 'user')).'.pdf',
        ];
    }
}
