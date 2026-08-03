@extends('layouts.gate')

@section('title', 'مسح QR — '.$program->title)
@section('container_width', 'max-w-xl')

{{-- Legacy scan view kept only as a safety fallback; controller redirects to portal. --}}
@section('content')
<div class="bg-white/95 rounded-3xl shadow-xl border border-white/80 p-6 text-center">
    <p class="text-sm text-gray-600">جارٍ التحويل إلى بوابة التحضير…</p>
    <a href="{{ route('gate.portal', ['program' => $program->slug, 'tab' => 'qr']) }}" class="mt-3 inline-block text-sm font-semibold text-[#335483] underline">
        فتح البوابة
    </a>
</div>
@endsection
