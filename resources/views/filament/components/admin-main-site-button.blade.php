@php
    use App\Support\MainSiteDashboardUrl;
    use Filament\Support\Enums\IconPosition;
    use Filament\Support\Icons\Heroicon;

    $href = MainSiteDashboardUrl::resolve();
    $isRtl = __('filament-panels::layout.direction') === 'rtl';
    $iconPosition = $isRtl ? IconPosition::After : IconPosition::Before;
@endphp

<div class="fi-admin-main-site-btn-ctn flex shrink-0 items-center" style="margin-inline-end: 0.5rem;">
    <x-filament::button
        tag="a"
        :href="$href"
        color="gray"
        outlined
        :icon="Heroicon::OutlinedHome"
        :icon-position="$iconPosition"
    >
        الانتقال للواجهة الرئيسية
    </x-filament::button>
</div>
