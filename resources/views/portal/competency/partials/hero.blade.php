@php
$p = $profile;
$loc = $cvLocale ?? 'ar';
$jobTitle = trim((string) ($p?->job_title ?? ''));
$city = trim((string) ($p?->city ?? ''));
$phone = trim((string) ($user->phone ?? ''));
$editProfileLabel = $loc === 'en' ? 'Edit profile' : 'تعديل الملف الشخصي';
$editProfileAria = $loc === 'en' ? 'Edit your profile: photo, name, city, title, email, and phone' : 'تعديل الملف الشخصي: الصورة والاسم والمدينة والمسمى والبريد والجوال';
@endphp
<header class="relative mb-8 overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
    <div class="absolute inset-0 opacity-[0.07]" style="background: linear-gradient(135deg, #253B5B 0%, #3CB878 100%);"></div>
    <div class="relative px-5 py-6 sm:px-8 sm:py-8 lg:px-10 lg:py-9">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between lg:gap-8">
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start sm:gap-5">
                    <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gray-200 text-2xl font-bold text-gray-600 ring-4 ring-white shadow-md sm:h-28 sm:w-28">
                        @if ($p?->avatarUrl())
                        <img src="{{ $p->avatarUrl() }}" alt="" class="h-full w-full object-cover" />
                        @else
                        {{ \App\Models\Profile::initialsFromName($user->name) }}
                        @endif
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-3 sm:gap-3">
                        <div class="flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <div class="min-w-0 flex-1 text-center sm:text-right">
                                <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">{{ $user->name }}</h1>
                                @if ($city !== '')
                                <p class="mt-1.5 text-sm text-gray-600">{{ ($cvLabels ?? [])['city'] ?? 'المدينة' }}: {{ $city }}</p>
                                @else
                                <p class="mt-1.5 text-sm text-gray-400">{{ $loc === 'en' ? 'No city yet' : 'لم تُضف المدينة بعد' }}</p>
                                @endif
                                @if ($jobTitle !== '')
                                <p class="mt-1.5 text-base font-semibold text-[#253B5B] sm:text-lg">{{ $jobTitle }}</p>
                                @else
                                <p class="mt-1.5 text-sm text-gray-400">{{ $loc === 'en' ? 'No title yet' : 'لم يُضف المسمى بعد' }}</p>
                                @endif
                                @if (filled($user->email))
                                <p class="mt-1 text-sm text-gray-500" dir="ltr">{{ $user->email }}</p>
                                @else
                                <p class="mt-1 text-sm text-gray-400">{{ $loc === 'en' ? 'No email on file' : 'لم يُضف البريد الإلكتروني بعد' }}</p>
                                @endif
                                @if ($phone !== '')
                                <p class="mt-1 text-sm text-gray-500" dir="ltr">{{ $phone }}</p>
                                @else
                                <p class="mt-1 text-sm text-gray-400">{{ $loc === 'en' ? 'No phone yet' : 'لم يُضف رقم الجوال بعد' }}</p>
                                @endif
                            </div>
                            <a
                                href="{{ route('portal.profile') }}"
                                class="inline-flex shrink-0 items-center justify-center gap-2 self-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-[#253B5B] shadow-sm transition hover:border-[#253B5B]/30 hover:bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#253B5B]/25 sm:self-start"
                                aria-label="{{ $editProfileAria }}"
                            >
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                {{ $editProfileLabel }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="flex w-full shrink-0 flex-col gap-2 lg:max-w-[220px] lg:pt-1">
                    <a href="{{ route('portal.competency.export-pdf') }}" target="_blank" rel="noopener noreferrer" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-3.5 text-sm font-semibold text-white shadow-md transition hover:opacity-95" style="background:#253B5B">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        {{ $loc === 'en' ? 'Export CV (PDF)' : 'تصدير كفاءتي كسيرة ذاتية' }}
                    </a>
                </div>
        </div>
    </div>
</header>
