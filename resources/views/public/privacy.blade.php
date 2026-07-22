@extends('layouts.public')

@section('title', ($policy->title ?? 'سياسة الخصوصية').' — كفاءات')
@section('meta_description', 'سياسة الخصوصية لجمعية كفاءات: كيف نجمع بياناتك ونستخدمها ونحميها، وحقوقك تجاه معلوماتك الشخصية.')

@section('content')

<div class="max-w-3xl mx-auto text-right">

    <div class="mb-10">
        <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color:#1a9399">الخصوصية والحماية</p>
        <h1 class="text-3xl sm:text-4xl font-bold mb-3">{{ $policy->title }}</h1>
        <p class="text-sm" style="color:#9CA3AF">
            الإصدار {{ $policy->version }}
            @if ($policy->published_at)
            · نشر: {{ ar_date($policy->published_at) }}
            @endif
        </p>
    </div>

    <div class="space-y-8 leading-relaxed privacy-policy-content" style="color:var(--brand-body)">
        {!! $sanitizedContent !!}
    </div>
</div>

@endsection
