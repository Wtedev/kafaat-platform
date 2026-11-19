@php
    use App\Enums\ProfileGender;

    $genders = old('acceptance_genders', $program?->acceptance_conditions['genders'] ?? []);
    $genders = is_array($genders) ? $genders : [];
    $showMale = $genders === [] || in_array(ProfileGender::Male->value, $genders, true);
    $showFemale = $genders === [] || in_array(ProfileGender::Female->value, $genders, true);
@endphp
<x-staff-ui.textarea name="approval_message" label="رسالة القبول" rows="6" hint="اتركها فارغة للرسالة الافتراضية.">{{ old('approval_message', $program?->approval_message) }}</x-staff-ui.textarea>
<x-staff-ui.toggle name="whatsapp_groups_enabled" value="1" label="مجموعات التواصل" :checked="(bool) old('whatsapp_groups_enabled', $program?->whatsapp_groups_enabled)" />
@if ($showMale)
    <x-staff-ui.input name="whatsapp_group_male" label="رابط مجموعة الرجال" :value="old('whatsapp_group_male', $program?->whatsapp_group_male)" hint="يبدأ بـ https://" :error="$errors->first('whatsapp_group_male')" />
@endif
@if ($showFemale)
    <x-staff-ui.input name="whatsapp_group_female" label="رابط مجموعة النساء" :value="old('whatsapp_group_female', $program?->whatsapp_group_female)" hint="يبدأ بـ https://" :error="$errors->first('whatsapp_group_female')" />
@endif
@if (! empty($previewMailAction))
    <x-staff-ui.button type="submit" :form="$formId" variant="secondary" size="sm" :formaction="$previewMailAction">أرسل نسخة تجريبية لي</x-staff-ui.button>
@endif
