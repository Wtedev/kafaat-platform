@php
    use Filament\Support\Enums\IconPosition;
    use Filament\Support\Icons\Heroicon;

    $isRtl = __('filament-panels::layout.direction') === 'rtl';
    $iconPosition = $isRtl ? IconPosition::After : IconPosition::Before;
    $user = auth()->user();
    $showTrainingPlatform = $user
        && $user->canAccessBeneficiaryPortal()
        && \Illuminate\Support\Facades\Route::has('portal.dashboard');
@endphp

<div class="fi-admin-main-site-btn-ctn flex shrink-0 items-center gap-2" style="margin-inline-end: 0.5rem;">
    <x-filament::button
        tag="a"
        :href="route('home')"
        color="gray"
        outlined
        :icon="Heroicon::OutlinedHome"
        :icon-position="$iconPosition"
    >
        الانتقال للواجهة الرئيسية
    </x-filament::button>

    @if ($showTrainingPlatform)
    <x-filament::button
        tag="a"
        :href="route('portal.dashboard')"
        color="gray"
        outlined
        :icon="Heroicon::OutlinedAcademicCap"
        :icon-position="$iconPosition"
    >
        منصة التدريب
    </x-filament::button>
    @endif
</div>
