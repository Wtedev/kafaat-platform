@extends('layouts.public')

@section('title', 'الحوكمة — كفاءات')

@section('head')
<style>
    .gov-tab-btn {
        position: relative;
        white-space: nowrap;
        transition: color 0.2s, background 0.2s;
    }
    .gov-tab-btn.active {
        color: #253B5B;
        font-weight: 700;
    }
    .gov-tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        right: 0;
        left: 0;
        height: 3px;
        border-radius: 9999px;
        background: #253B5B;
    }
    .gov-tab-panel { display: none; }
    .gov-tab-panel.active { display: block; }

    .doc-card {
        transition: transform 0.25s cubic-bezier(.22,1,.36,1), box-shadow 0.25s cubic-bezier(.22,1,.36,1);
    }
    .doc-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 40px rgba(37,59,91,0.10);
    }
    .member-card {
        transition: transform 0.25s cubic-bezier(.22,1,.36,1), box-shadow 0.25s cubic-bezier(.22,1,.36,1);
    }
    .member-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 48px rgba(37,59,91,0.12);
    }
</style>
@endsection

@section('content')

{{-- Page Header --}}
<div class="text-right mb-8">
    <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color:#3CB878">الشفافية والمساءلة</p>
    <h1 class="text-3xl sm:text-4xl font-bold mb-3" style="color:#111827">الحوكمة</h1>
    <p class="text-base leading-relaxed max-w-2xl" style="color:#6B7280">
        نلتزم بأعلى معايير الحوكمة والشفافية. تصفّح وثائق الجمعية وتقاريرها وهيكلها التنظيمي.
    </p>
</div>

{{-- Tabs Navigation --}}
@php
$tabs = [
    'board'                   => 'أعضاء مجلس الإدارة',
    'organizational_structure'=> 'الهيكل التنظيمي',
    'investment_decisions'    => 'القرارات الاستثمارية',
    'general_assembly_minutes'=> 'محاضر الجمعية العمومية',
    'surveys'                 => 'استطلاعات',
    'executive_reports'       => 'التقارير التنفيذية',
    'financial_reports'       => 'التقارير المالية',
];
@endphp

<div class="mb-8 border-b border-gray-200">
    <div class="overflow-x-auto">
        <div class="flex gap-1 min-w-max pb-0.5" id="gov-tabs-nav">
            @foreach($tabs as $key => $label)
            <button
                class="gov-tab-btn px-4 py-3 text-sm font-medium rounded-t-xl hover:bg-gray-50 {{ $loop->first ? 'active' : '' }}"
                style="color:#6B7280"
                data-tab="{{ $key }}"
                onclick="switchGovTab('{{ $key }}', this)">
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>
</div>

{{-- Tab Panels --}}

{{-- Board Members --}}
<div id="tab-board" class="gov-tab-panel active">
    @if($boardMembers->isEmpty())
    <div class="text-center py-20">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#EAF2FA">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#253B5B"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <h3 class="text-lg font-semibold mb-1" style="color:#374151">لم يتم إضافة أعضاء مجلس الإدارة بعد</h3>
        <p class="text-sm" style="color:#9CA3AF">سيتم إضافة المحتوى قريباً.</p>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @foreach($boardMembers as $member)
        <div class="member-card bg-white rounded-2xl border border-gray-100 shadow-sm p-6 text-center flex flex-col items-center">
            {{-- Avatar --}}
            @if($member->photoPublicUrl())
            <img src="{{ $member->photoPublicUrl() }}"
                 alt="{{ $member->name }}"
                 class="w-20 h-20 rounded-full object-cover mb-4 border-2 border-gray-100 shadow-sm" />
            @else
            <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4 border-2 border-gray-100 shadow-sm" style="background:#EAF2FA">
                <svg class="w-9 h-9" fill="none" viewBox="0 0 24 24" stroke="#253B5B"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            @endif

            <h3 class="text-base font-bold mb-1" style="color:#111827">{{ $member->name }}</h3>
            @if($member->role)
            <p class="text-xs font-medium mb-3 px-3 py-1 rounded-full" style="background:#EAF2FA; color:#253B5B">{{ $member->role }}</p>
            @endif
            @if($member->bio)
            <p class="text-sm leading-relaxed" style="color:#6B7280">{{ Str::limit($member->bio, 140) }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- Document-based tabs --}}
@foreach(['organizational_structure','investment_decisions','general_assembly_minutes','surveys','executive_reports','financial_reports'] as $type)
<div id="tab-{{ $type }}" class="gov-tab-panel">
    @php $docs = $documents[$type] ?? collect(); @endphp
    @if($docs->isEmpty())
    <div class="text-center py-20">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#EAF2FA">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#253B5B"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
        </div>
        <h3 class="text-lg font-semibold mb-1" style="color:#374151">لا توجد وثائق منشورة حالياً</h3>
        <p class="text-sm" style="color:#9CA3AF">سيتم إضافة المحتوى قريباً.</p>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($docs as $doc)
        <div class="doc-card bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-right flex flex-col">
            {{-- Cover image --}}
            @if($doc->coverImageUrl())
            <img src="{{ $doc->coverImageUrl() }}"
                 alt="{{ $doc->title }}"
                 class="w-full h-36 object-cover rounded-xl mb-4" />
            @endif

            {{-- Date badge --}}
            @if($doc->document_date)
            <span class="text-xs font-medium mb-2 inline-block" style="color:#9CA3AF">
                {{ $doc->document_date->translatedFormat('d F Y') }}
            </span>
            @endif

            <h3 class="text-base font-bold mb-2 leading-snug flex-1" style="color:#111827">{{ $doc->title }}</h3>

            @if($doc->description)
            <p class="text-sm leading-relaxed mb-4" style="color:#6B7280">{{ Str::limit($doc->description, 120) }}</p>
            @endif

            @php $url = $doc->filePublicUrl(); @endphp
            @if($url)
            <a href="{{ $url }}"
               target="_blank"
               rel="noopener noreferrer"
               class="mt-auto inline-flex items-center gap-2 text-sm font-semibold rounded-xl px-4 py-2 transition-all duration-200 hover:-translate-y-0.5"
               style="background:#EAF2FA; color:#253B5B">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                تنزيل / عرض
            </a>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
@endforeach

@endsection

@section('scripts')
<script>
function switchGovTab(key, btn) {
    // Hide all panels
    document.querySelectorAll('.gov-tab-panel').forEach(function(el) {
        el.classList.remove('active');
    });
    // Deactivate all tabs
    document.querySelectorAll('.gov-tab-btn').forEach(function(el) {
        el.classList.remove('active');
        el.style.color = '#6B7280';
    });
    // Show selected panel
    var panel = document.getElementById('tab-' + key);
    if (panel) panel.classList.add('active');
    // Activate selected tab
    if (btn) {
        btn.classList.add('active');
        btn.style.color = '#253B5B';
    }
}

// Support deep-linking via URL hash
(function() {
    var hash = window.location.hash.replace('#', '');
    if (hash) {
        var btn = document.querySelector('[data-tab="' + hash + '"]');
        if (btn) switchGovTab(hash, btn);
    }
})();
</script>
@endsection
