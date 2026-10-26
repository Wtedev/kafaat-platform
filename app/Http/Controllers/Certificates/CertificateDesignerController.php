<?php

namespace App\Http\Controllers\Certificates;

use App\Enums\CertificateFieldKey;
use App\Filament\Resources\TrainingProgramResource;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramRegistrationCertificatesRelationManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Certificates\SaveCertificateDesignRequest;
use App\Http\Requests\Certificates\StoreCertificateBackgroundRequest;
use App\Models\TrainingProgram;
use App\Services\CertificatePdfService;
use App\Services\Certificates\CertificateDesignService;
use App\Services\Certificates\CertificateFieldValues;
use App\Services\Certificates\CertificateQr;
use App\Support\PublicDiskPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CertificateDesignerController extends Controller
{
    public function __construct(
        private readonly CertificateDesignService $designs,
    ) {}

    public function edit(Request $request, TrainingProgram $program): View
    {
        $this->authorizeDesigner($request, $program);
        $program->loadMissing('certificateTemplate');
        $template = $program->certificateTemplate;
        $samples = CertificateFieldValues::forPreview($program);

        return view('certificate-designer.edit', [
            'program' => $program,
            'backUrl' => $this->certificatesTabUrl($program),
            'designer' => [
                'saveUrl' => route('certificate-designer.update', $program),
                'backgroundUrl' => route('certificate-designer.background', $program),
                'previewUrl' => route('certificate-designer.preview', $program),
                'samples' => $samples,
                'qrDataUri' => CertificateQr::dataUri($samples[CertificateFieldKey::VerificationQr->value]),
                'elements' => $template?->elements ?? [],
                'hours' => $template?->hours,
                'backgroundUrlImage' => PublicDiskPath::url($template?->background_path),
                'fields' => array_map(
                    fn (CertificateFieldKey $field): array => [
                        'key' => $field->value,
                        'label' => $field->label(),
                        'qr' => $field->isQr(),
                    ],
                    CertificateFieldKey::cases(),
                ),
            ],
        ]);
    }

    public function update(SaveCertificateDesignRequest $request, TrainingProgram $program): JsonResponse
    {
        $this->authorizeDesigner($request, $program);

        $template = $this->designs->save(
            $program,
            $request->user(),
            $request->input('hours'),
            $request->input('elements', []),
        );

        return response()->json([
            'message' => 'تم حفظ تصميم الشهادة.',
            'hours' => $template->hours,
        ]);
    }

    public function background(StoreCertificateBackgroundRequest $request, TrainingProgram $program): JsonResponse
    {
        $this->authorizeDesigner($request, $program);

        $template = $this->designs->storeBackground(
            $program,
            $request->user(),
            $request->file('background'),
        );

        return response()->json([
            'message' => 'تم رفع خلفية الشهادة.',
            'backgroundUrl' => PublicDiskPath::url($template->background_path),
        ]);
    }

    public function preview(Request $request, TrainingProgram $program): Response
    {
        $this->authorizeDesigner($request, $program);

        return app(CertificatePdfService::class)->previewResponse($program);
    }

    private function authorizeDesigner(Request $request, TrainingProgram $program): void
    {
        abort_unless($request->user()?->can('designCertificate', $program) ?? false, 403);
    }

    private function certificatesTabUrl(TrainingProgram $program): string
    {
        $relations = array_values(TrainingProgramResource::getRelations());
        $index = array_search(ProgramRegistrationCertificatesRelationManager::class, $relations, true);
        $url = url('/admin/training-programs/'.$program->getKey());

        if ($index === false) {
            return $url;
        }

        return $url.'?relation='.$index;
    }
}
