@php
    use App\Filament\Pages\StaffProfilePage;

    $user = auth()->user();
@endphp

@if ($user !== null && $user->canAccessFilamentAdmin())
@php
    $user->loadMissing('roles');
    $photoUrl = $user->staffPhotoUrl();
    $rolesLabel = $user->filamentStaffRoleLabelsAr();
    $profileUrl = StaffProfilePage::getUrl();
@endphp

<div
    @if (filament()->isSidebarCollapsibleOnDesktop() || filament()->isSidebarFullyCollapsibleOnDesktop())
        x-show="$store.sidebar.isOpen"
        x-cloak
    @endif
    class="fi-admin-sidebar-staff-wrap px-3 pb-2 pt-1.5"
>
    <div class="fi-admin-sidebar-staff-card relative max-w-full shadow-sm">
        <a
            href="{{ $profileUrl }}"
            wire:navigate
            class="fi-admin-sidebar-staff-menu inline-flex size-9 items-center justify-center rounded-lg transition-colors"
            title="الملف الشخصي"
            aria-label="قائمة الملف الشخصي"
        >
            <span class="select-none text-lg leading-none" aria-hidden="true">&#8942;</span>
        </a>

        <div dir="rtl" class="admin-sidebar-profile min-w-0">
            <div
                class="admin-sidebar-profile-avatar"
                @if ($photoUrl)
                    style="--admin-sidebar-avatar-bg: url({{ \Illuminate\Support\Js::from($photoUrl) }})"
                @endif
            >
                <a
                    href="{{ $profileUrl }}"
                    wire:navigate
                    class="admin-sidebar-profile-avatar-link"
                    aria-label="الملف الشخصي"
                >
                    @if ($photoUrl)
                        <img
                            src="{{ $photoUrl }}"
                            alt=""
                            width="64"
                            height="64"
                            class="admin-sidebar-profile-img"
                            decoding="async"
                        />
                    @else
                        <span class="fi-admin-sidebar-staff-avatar-fallback" aria-hidden="true">
                            {{ mb_substr((string) $user->name, 0, 1) }}
                        </span>
                    @endif
                </a>
            </div>

            <div class="admin-sidebar-profile-info">
                <a
                    href="{{ $profileUrl }}"
                    wire:navigate
                    class="admin-sidebar-profile-info-link block min-w-0 transition-opacity hover:opacity-90"
                >
                    <div class="admin-sidebar-profile-name fi-admin-sidebar-staff-name truncate text-sm font-semibold leading-snug">
                        {{ $user->name }}
                    </div>
                    <div class="admin-sidebar-profile-role fi-admin-sidebar-staff-role mt-0.5 truncate text-xs leading-snug">
                        {{ $rolesLabel }}
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endif
