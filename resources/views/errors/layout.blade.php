@extends('layouts.auth')
@section('title', $title ?? 'خطأ')
@section('content')
<div class="text-center">
    <p class="text-5xl font-bold text-brand mb-2">{{ $code ?? '—' }}</p>
    <h1 class="text-lg font-semibold text-gray-800 mb-2">{{ $title ?? 'حدث خطأ' }}</h1>
    <p class="text-sm text-gray-600 mb-4">{{ $message ?? 'يرجى المحاولة لاحقاً.' }}</p>
    @if(!empty($requestId))
        <p class="text-xs text-gray-400 mb-4">مرجع الطلب: {{ $requestId }}</p>
    @endif
    <a href="{{ route('home') }}" class="text-sm text-brand hover:underline">العودة للرئيسية</a>
</div>
@endsection
