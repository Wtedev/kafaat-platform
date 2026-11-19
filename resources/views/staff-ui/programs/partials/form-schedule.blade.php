@php
    use App\Enums\TrainingProgramKind;

    $isSession = ($program?->program_kind ?? null) === TrainingProgramKind::Session
        || old('program_kind') === TrainingProgramKind::Session->value;
@endphp
<div class="sui-form-grid">
    <x-staff-ui.input type="date" name="start_date" label="تاريخ البداية" :value="old('start_date', $program?->start_date?->toDateString())" :error="$errors->first('start_date')" />
    @unless ($isSession)
        <x-staff-ui.input type="date" name="end_date" label="تاريخ النهاية" :value="old('end_date', $program?->end_date?->toDateString())" :error="$errors->first('end_date')" />
    @endunless
    <x-staff-ui.input type="date" name="registration_start" label="بداية التسجيل" :value="old('registration_start', $program?->registration_start?->toDateString())" />
    <x-staff-ui.input type="date" name="registration_end" label="نهاية التسجيل" :value="old('registration_end', $program?->registration_end?->toDateString())" :error="$errors->first('registration_end')" />
</div>
