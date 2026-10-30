{{-- Inline styles: this view renders inside Filament, which does not compile the app's Tailwind classes. --}}
<div style="display: flex; flex-wrap: wrap; align-items: center; column-gap: 1.5rem; row-gap: 0.25rem; font-size: 0.875rem;">
    <span>المقبولون: <strong>{{ $accepted }}</strong></span>
    <span>الصادرة: <strong>{{ $issued }}</strong></span>
    <span>المتبقون: <strong>{{ $remaining }}</strong></span>
</div>
