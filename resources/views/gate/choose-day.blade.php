@extends('layouts.gate')

@section('title', 'اختيار يوم التحضير — '.$program->title)
@section('container_width', 'max-w-xl')

@section('content')
<div class="space-y-4">
    <div class="bg-white/95 rounded-3xl shadow-xl border border-white/80 p-6 sm:p-7">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-gray-900 leading-snug">{{ $program->title }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    المتحضّرة:
                    <span class="font-semibold text-[#335483]">{{ $operatorName }}</span>
                    @if ($operatorType === 'admin')
                        <span class="text-xs text-gray-400">(أدمن)</span>
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('gate.logout', ['program' => $program->slug]) }}">
                @csrf
                <button type="submit" class="text-xs font-medium text-gray-500 hover:text-gray-800 underline-offset-2 hover:underline">
                    خروج
                </button>
            </form>
        </div>

        <h2 class="mt-6 text-base font-bold text-gray-900">اختاري يوم التحضير</h2>
        <p class="mt-1 text-sm text-gray-600">يجب تحديد اليوم قبل بدء المسح عندما يكون هناك أكثر من يوم تحضير.</p>

        @if (! empty($emptyMessage ?? null))
            <div class="mt-5 rounded-2xl border px-4 py-4 text-center {{ config('brand.classes.alert_danger') }}">
                <p class="text-sm">{{ $emptyMessage }}</p>
            </div>
        @elseif ($options === [])
            <div class="mt-5 rounded-2xl border px-4 py-4 text-center {{ config('brand.classes.alert_danger') }}">
                <p class="text-sm">لا توجد أيام تحضير مفعّلة.</p>
            </div>
        @else
            <form method="POST" action="{{ route('gate.scan.day', ['program' => $program->slug]) }}" class="mt-5 space-y-3">
                @csrf
                <label for="date" class="block text-sm font-medium text-gray-700">يوم التحضير</label>
                <select
                    id="date"
                    name="date"
                    required
                    class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25"
                >
                    @foreach ($options as $value => $label)
                        <option value="{{ $value }}" @selected($suggested === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('date')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
                <button type="submit" class="w-full py-3 rounded-xl bg-brand text-white font-semibold text-sm hover:opacity-95 transition">
                    متابعة إلى المسح
                </button>
            </form>
        @endif
    </div>
</div>
@endsection
