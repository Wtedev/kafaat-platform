<?php

namespace App\Console\Commands;

use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\ProgramRegistration;
use App\Models\VolunteerHour;
use App\Models\VolunteerRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanQaData extends Command
{
    protected $signature = 'qa:clean {--force : Skip confirmation prompt}';

    protected $description = 'Remove QA/test records created during development testing';

    public function handle(): int
    {
        $this->info('=== QA Data Cleanup ===');
        $this->newLine();

        // --- Collect what will be deleted ---

        // 1. Certificates: CERT-TEST-* prefix or manually created without a PDF
        $testCerts = Certificate::where('certificate_number', 'like', 'CERT-TEST-%')
            ->orWhere(fn ($q) => $q->whereNull('file_path')
                ->whereDate('issued_at', now()->toDateString())
            )
            ->get();

        // 2. All volunteer hours (created only during QA in this project)
        $qaHours = VolunteerHour::all();

        // 3. Volunteer registrations that were created during QA (completed ones driven by QA hours)
        $qaVolRegs = VolunteerRegistration::whereIn(
            'status',
            [RegistrationStatus::Completed]
        )->get();

        // 4. Program registrations created during QA (completed or non-seeded ones)
        //    Conservative: only those with IDs > 6 (seeded data is typically IDs 1-6)
        $qaProgRegs = ProgramRegistration::where('id', '>', 6)->get();

        // --- Print summary ---
        $this->warn('The following records will be deleted:');
        $this->newLine();

        $this->table(
            ['Type', 'ID', 'Details'],
            collect()
                ->merge($testCerts->map(fn ($c) => [
                    'Certificate',
                    $c->id,
                    "{$c->certificate_number} (user_id={$c->user_id})",
                ]))
                ->merge($qaHours->map(fn ($h) => [
                    'VolunteerHour',
                    $h->id,
                    "user_id={$h->user_id}, hours={$h->hours}, status={$h->status->value}",
                ]))
                ->merge($qaVolRegs->map(fn ($r) => [
                    'VolunteerRegistration',
                    $r->id,
                    "user_id={$r->user_id}, status={$r->status->value}",
                ]))
                ->merge($qaProgRegs->map(fn ($r) => [
                    'ProgramRegistration',
                    $r->id,
                    "user_id={$r->user_id}, status={$r->status->value}",
                ]))
                ->toArray()
        );

        $total = $testCerts->count() + $qaHours->count() + $qaVolRegs->count() + $qaProgRegs->count();

        if ($total === 0) {
            $this->info('No QA data found. Nothing to delete.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("Total records to delete: {$total}");
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Proceed with deletion?', false)) {
            $this->info('Aborted. No data was deleted.');

            return self::SUCCESS;
        }

        // --- Delete PDF files for test certs ---
        foreach ($testCerts as $cert) {
            if ($cert->file_path) {
                Storage::delete($cert->file_path);
            }
        }

        // --- Delete records ---
        Certificate::whereIn('id', $testCerts->pluck('id'))->delete();
        VolunteerHour::whereIn('id', $qaHours->pluck('id'))->delete();
        VolunteerRegistration::whereIn('id', $qaVolRegs->pluck('id'))->delete();
        ProgramRegistration::whereIn('id', $qaProgRegs->pluck('id'))->delete();

        $this->info("Deleted {$testCerts->count()} certificate(s).");
        $this->info("Deleted {$qaHours->count()} volunteer hour(s).");
        $this->info("Deleted {$qaVolRegs->count()} volunteer registration(s).");
        $this->info("Deleted {$qaProgRegs->count()} program registration(s).");
        $this->newLine();
        $this->info('QA data cleanup complete.');

        return self::SUCCESS;
    }
}
