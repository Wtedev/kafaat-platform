@php
    use App\Enums\ProfileGender;

    $genders = old('acceptance_genders', $program?->acceptance_conditions['genders'] ?? []);
    $genders = is_array($genders) ? $genders : [];
    $showMale = $genders === [] || in_array(ProfileGender::Male->value, $genders, true);
    $showFemale = $genders === [] || in_array(ProfileGender::Female->value, $genders, true);
    $capacityMode = old('capacity_mode', $program?->capacity_male || $program?->capacity_female ? 'per_gender' : ($program?->capacity ? 'shared' : 'unlimited'));
@endphp
<x-staff-ui.toggle name="acceptance_require_saudi_national" value="1" label="سعوديون فقط" :checked="(bool) old('acceptance_require_saudi_national', $program?->acceptance_conditions['require_saudi_national'] ?? false)" />
<h2 class="sui-wizard-sub">الجنس</h2>
<p class="sui-wizard-hint">اترك الاختيار فارغاً لقبول الجميع.</p>
@foreach ($genderOptions as $value => $label)
    <x-staff-ui.checkbox name="acceptance_genders[]" :value="$value" :label="$label" :checked="in_array($value, $genders, true)" />
@endforeach
<div class="sui-form-grid">
    <x-staff-ui.input type="number" name="acceptance_min_age" label="الحد الأدنى للعمر" min="0" max="120" :value="old('acceptance_min_age', $program?->acceptance_conditions['min_age'] ?? '')" />
    <x-staff-ui.input type="number" name="acceptance_max_age" label="الحد الأقصى للعمر" min="0" max="120" :value="old('acceptance_max_age', $program?->acceptance_conditions['max_age'] ?? '')" :error="$errors->first('acceptance_max_age')" />
</div>
<x-staff-ui.select name="capacity_mode" label="السعة" :selected="$capacityMode" :options="['unlimited' => 'بلا حد', 'shared' => 'سعة واحدة', 'per_gender' => 'سعة لكل جنس']" />
<div data-sui-capacity="shared" @if ($capacityMode !== 'shared') hidden @endif>
    <x-staff-ui.input type="number" name="capacity" label="السعة" min="1" :value="old('capacity', $program?->capacity)" :error="$errors->first('capacity')" />
</div>
<div data-sui-capacity="male" @if ($capacityMode !== 'per_gender' || ! $showMale) hidden @endif>
    <x-staff-ui.input type="number" name="capacity_male" label="سعة الرجال" min="1" :value="old('capacity_male', $program?->capacity_male)" :error="$errors->first('capacity_male')" />
</div>
<div data-sui-capacity="female" @if ($capacityMode !== 'per_gender' || ! $showFemale) hidden @endif>
    <x-staff-ui.input type="number" name="capacity_female" label="سعة النساء" min="1" :value="old('capacity_female', $program?->capacity_female)" :error="$errors->first('capacity_female')" />
</div>
<x-staff-ui.toggle name="auto_accept_registrations" value="1" label="قبول تلقائي لمن يستوفي الشروط حتى امتلاء السعة" :checked="(bool) old('auto_accept_registrations', $program?->auto_accept_registrations)" />
<h2 class="sui-wizard-sub">كما ستظهر للمستفيد</h2>
<ul data-sui-acceptance-preview>
    @foreach ($acceptancePreview as $line)
        <li>{{ $line }}</li>
    @endforeach
</ul>
