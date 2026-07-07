<?php

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\TrainingProgramKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يُنظّف قيم enum غير المعروفة في الإنتاج (تسبب 500 عند تحميل نماذج Filament).
 */
return new class extends Migration
{
    public function up(): void
    {
        $validTracks = array_map(
            static fn (CompetencyTrack $track): string => $track->value,
            CompetencyTrack::cases(),
        );

        $validProgramKinds = array_map(
            static fn (TrainingProgramKind $kind): string => $kind->value,
            TrainingProgramKind::cases(),
        );

        $validDeliveryModes = array_map(
            static fn (ProgramDeliveryMode $mode): string => $mode->value,
            ProgramDeliveryMode::cases(),
        );

        if (Schema::hasTable('learning_paths') && Schema::hasColumn('learning_paths', 'competency_track')) {
            DB::table('learning_paths')
                ->whereNotNull('competency_track')
                ->whereNotIn('competency_track', $validTracks)
                ->update(['competency_track' => null]);
        }

        if (Schema::hasTable('training_programs')) {
            if (Schema::hasColumn('training_programs', 'competency_track')) {
                DB::table('training_programs')
                    ->whereNotNull('competency_track')
                    ->whereNotIn('competency_track', $validTracks)
                    ->update(['competency_track' => null]);
            }

            if (Schema::hasColumn('training_programs', 'program_kind')) {
                DB::table('training_programs')
                    ->where('program_kind', 'bootcamp')
                    ->update(['program_kind' => TrainingProgramKind::Workshop->value]);

                DB::table('training_programs')
                    ->whereNotNull('program_kind')
                    ->whereNotIn('program_kind', $validProgramKinds)
                    ->update(['program_kind' => TrainingProgramKind::Course->value]);
            }

            if (Schema::hasColumn('training_programs', 'delivery_mode')) {
                DB::table('training_programs')
                    ->whereNotNull('delivery_mode')
                    ->whereNotIn('delivery_mode', $validDeliveryModes)
                    ->update(['delivery_mode' => null]);
            }
        }
    }

    public function down(): void
    {
        // لا استرجاع لقيم غير صالحة.
    }
};
