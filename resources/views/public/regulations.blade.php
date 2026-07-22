@extends('layouts.public')

@section('title', 'اللوائح والأنظمة — كفاءات')
@section('meta_description', 'تعرّف على اللوائح والأنظمة التي تحكم عمل جمعية كفاءات وتضمن الشفافية والمساءلة في جميع مستوياتها.')

@section('head')
<style>
    .reg-card {
        transition: transform 0.25s cubic-bezier(.22,1,.36,1), box-shadow 0.25s cubic-bezier(.22,1,.36,1);
    }
    .reg-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 40px rgba(51,84,131,0.10);
    }
</style>
@endsection

@section('content')

{{-- Page Header --}}
<div class="text-right mb-10">
    <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color:#1a9399">الشفافية والامتثال</p>
    <h1 class="text-3xl sm:text-4xl font-bold mb-3">اللوائح والأنظمة</h1>
    <p class="text-base leading-relaxed max-w-2xl" style="color:#6B7280">
        تعرّف على اللوائح والأنظمة التي تحكم عمل الجمعية وتضمن الشفافية والمساءلة في جميع مستوياتها.
    </p>
</div>

@if($regulations->isEmpty())
    {{-- Empty state --}}
    <div class="text-center py-24">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#e9eff6">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <h2 class="text-xl font-semibold mb-2">لا توجد لوائح منشورة حالياً</h2>
        <p class="text-sm" style="color:#9CA3AF">تابعنا قريباً لمزيد من المستجدات.</p>
    </div>
@else
    @foreach($regulations as $category => $items)
        <div class="mb-12">
            {{-- Category heading --}}
            @if($category)
            <div class="flex items-center gap-3 mb-6">
                <div class="w-1 h-7 rounded-full" style="background:#335483"></div>
                <h2 class="text-xl font-bold">{{ $category }}</h2>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($items as $regulation)
                <div class="reg-card bg-white rounded-2xl border border-gray-100 shadow-sm p-6 text-right flex flex-col">
                    {{-- Icon --}}
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 flex-shrink-0" style="background:#e9eff6">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>

                    {{-- Title --}}
                    <h3 class="text-base font-bold mb-2 leading-snug">{{ $regulation->title }}</h3>

                    {{-- Description --}}
                    @if($regulation->description)
                    <p class="text-sm leading-relaxed mb-4 flex-1" style="color:#6B7280">{{ Str::limit($regulation->description, 120) }}</p>
                    @else
                    <div class="flex-1"></div>
                    @endif

                    {{-- Download / View link --}}
                    @php $fileUrl = $regulation->filePublicUrl(); @endphp
                    @if($fileUrl)
                    <a href="{{ $fileUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="mt-2 inline-flex items-center gap-2 text-sm font-semibold rounded-xl px-4 py-2 transition-all duration-200 hover:-translate-y-0.5"
                       style="background:#e9eff6; color:#335483">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        عرض
                    </a>
                    @else
                    <span class="mt-2 inline-flex items-center gap-2 text-sm rounded-xl px-4 py-2" style="background:#F3F4F6; color:#9CA3AF">
                        قريباً
                    </span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endif

@endsection
