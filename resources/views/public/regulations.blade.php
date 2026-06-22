@extends('layouts.public')

@section('title', 'اللوائح والأنظمة — كفاءات')

@section('head')
<style>
    .reg-card {
        transition: transform 0.25s cubic-bezier(.22,1,.36,1), box-shadow 0.25s cubic-bezier(.22,1,.36,1);
    }
    .reg-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 40px rgba(37,59,91,0.10);
    }
</style>
@endsection

@section('content')

{{-- Page Header --}}
<div class="text-right mb-10">
    <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color:#3CB878">الشفافية والامتثال</p>
    <h1 class="text-3xl sm:text-4xl font-bold mb-3" style="color:#111827">اللوائح والأنظمة</h1>
    <p class="text-base leading-relaxed max-w-2xl" style="color:#6B7280">
        تعرّف على اللوائح والأنظمة التي تحكم عمل الجمعية وتضمن الشفافية والمساءلة في جميع مستوياتها.
    </p>
</div>

@if($regulations->isEmpty())
    {{-- Empty state --}}
    <div class="text-center py-24">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#EAF2FA">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#253B5B"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <h2 class="text-xl font-semibold mb-2" style="color:#374151">لا توجد لوائح منشورة حالياً</h2>
        <p class="text-sm" style="color:#9CA3AF">تابعنا قريباً لمزيد من المستجدات.</p>
    </div>
@else
    @foreach($regulations as $category => $items)
        <div class="mb-12">
            {{-- Category heading --}}
            @if($category)
            <div class="flex items-center gap-3 mb-6">
                <div class="w-1 h-7 rounded-full" style="background:#253B5B"></div>
                <h2 class="text-xl font-bold" style="color:#111827">{{ $category }}</h2>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($items as $regulation)
                <div class="reg-card bg-white rounded-2xl border border-gray-100 shadow-sm p-6 text-right flex flex-col">
                    {{-- Icon --}}
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 flex-shrink-0" style="background:#EAF2FA">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="#253B5B"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>

                    {{-- Title --}}
                    <h3 class="text-base font-bold mb-2 leading-snug" style="color:#111827">{{ $regulation->title }}</h3>

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
                       style="background:#EAF2FA; color:#253B5B">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        تنزيل / عرض
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
