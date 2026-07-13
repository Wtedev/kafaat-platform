<x-filament-panels::page>
    @php
        $staffUsers = $this->staffUsers();
        $sections = $this->sectionsWithGroups();
        $actionLabels = $this->actionLabels();
        $active = $this->activeStaff();
        $granted = $this->activePermissionCount();
        $total = $this->totalAssignableCount();
        $pct = $total > 0 ? (int) round(($granted / $total) * 100) : 0;
    @endphp

    <div class="spm" dir="rtl" wire:key="spm-root-{{ $activeStaffId ?? 'none' }}">
        <header class="spm-hero">
            <div class="spm-hero__text">
                <p class="spm-hero__eyebrow">الأمان والامتثال</p>
                <h2 class="spm-hero__title">مصفوفة صلاحيات الموظفين</h2>
                <p class="spm-hero__desc">
                    متاحة لحساب الأدمن فقط. اختر موظفاً، ثم فعّل صلاحيات العرض والإنشاء والتعديل والحذف لكل مجموعة، واحفظ عند الانتهاء.
                </p>
            </div>
            @if ($active)
                <div class="spm-hero__stats" aria-label="ملخص الصلاحيات">
                    <div class="spm-stat">
                        <span class="spm-stat__value">{{ $granted }}</span>
                        <span class="spm-stat__label">صلاحية مفعّلة</span>
                    </div>
                    <div class="spm-stat spm-stat--muted">
                        <span class="spm-stat__value">{{ $pct }}%</span>
                        <span class="spm-stat__label">من إجمالي المصفوفة</span>
                    </div>
                </div>
            @endif
        </header>

        @if ($staffUsers->isEmpty() && $this->staffSearch === '' && $activeStaffId === null)
            <div class="spm-empty">
                <div class="spm-empty__icon" aria-hidden="true">
                    <x-heroicon-o-user-group class="h-8 w-8" />
                </div>
                <h3 class="spm-empty__title">لا يوجد موظفون نشطون</h3>
                <p class="spm-empty__desc">
                    أضف مستخدماً بنوع «موظف» من قائمة المستخدمين، وسيحصل تلقائياً على كل صلاحيات المصفوفة.
                </p>
            </div>
        @else
            <div class="spm-layout">
                <aside class="spm-staff-panel">
                    <div class="spm-staff-panel__head">
                        <h3 class="spm-staff-panel__title">الموظفون</h3>
                        <div class="spm-search">
                            <x-heroicon-o-magnifying-glass class="spm-search__icon" />
                            <input
                                type="search"
                                wire:model.live.debounce.250ms="staffSearch"
                                class="spm-search__input"
                                placeholder="بحث بالاسم أو البريد…"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <div class="spm-staff-list" role="listbox" aria-label="قائمة الموظفين">
                        @forelse ($staffUsers as $staff)
                            @php
                                $isActive = $activeStaffId === $staff->id;
                                $photo = $staff->staffPhotoUrl();
                                $initial = mb_substr((string) $staff->name, 0, 1);
                            @endphp
                            <button
                                type="button"
                                role="option"
                                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                                wire:click="selectStaff({{ $staff->id }})"
                                @class(['spm-staff-item', 'is-active' => $isActive])
                            >
                                <span class="spm-staff-item__avatar" aria-hidden="true">
                                    @if ($photo)
                                        <img src="{{ $photo }}" alt="">
                                    @else
                                        <span>{{ $initial }}</span>
                                    @endif
                                </span>
                                <span class="spm-staff-item__meta">
                                    <span class="spm-staff-item__name">{{ $staff->name }}</span>
                                    <span class="spm-staff-item__email" dir="ltr">{{ $staff->email }}</span>
                                </span>
                                @if ($isActive)
                                    <span class="spm-staff-item__check" aria-hidden="true">
                                        <x-heroicon-s-check class="h-4 w-4" />
                                    </span>
                                @endif
                            </button>
                        @empty
                            <div class="spm-staff-empty">لا نتائج مطابقة للبحث</div>
                        @endforelse
                    </div>
                </aside>

                <section class="spm-workspace">
                    @if (! $active)
                        <div class="spm-empty spm-empty--panel">
                            <h3 class="spm-empty__title">اختر موظفاً</h3>
                            <p class="spm-empty__desc">حدّد موظفاً من القائمة لعرض مصفوفة صلاحياته وتعديلها.</p>
                        </div>
                    @else
                        <div class="spm-toolbar sticky top-0 z-20">
                            <div class="spm-toolbar__identity">
                                <span class="spm-toolbar__avatar" aria-hidden="true">
                                    @if ($active->staffPhotoUrl())
                                        <img src="{{ $active->staffPhotoUrl() }}" alt="">
                                    @else
                                        <span>{{ mb_substr((string) $active->name, 0, 1) }}</span>
                                    @endif
                                </span>
                                <div>
                                    <p class="spm-toolbar__name">{{ $active->name }}</p>
                                    <p class="spm-toolbar__email" dir="ltr">{{ $active->email }}</p>
                                </div>
                                @if ($isDirty)
                                    <span class="spm-dirty-badge">تعديلات غير محفوظة</span>
                                @endif
                            </div>

                            <div class="spm-toolbar__actions">
                                <div class="spm-bulk" role="group" aria-label="تحديد جماعي">
                                    <button type="button" wire:click="grantAll" class="spm-btn spm-btn--ghost">
                                        تحديد الكل
                                    </button>
                                    <button type="button" wire:click="clearAll" class="spm-btn spm-btn--ghost">
                                        إلغاء الكل
                                    </button>
                                </div>
                                @if ($isDirty)
                                    <button type="button" wire:click="discardChanges" class="spm-btn spm-btn--ghost">
                                        تراجع
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    wire:click="save"
                                    wire:loading.attr="disabled"
                                    @class(['spm-btn', 'spm-btn--primary', 'is-dirty' => $isDirty])
                                >
                                    <span wire:loading.remove wire:target="save">حفظ الصلاحيات</span>
                                    <span wire:loading wire:target="save">جاري الحفظ…</span>
                                </button>
                            </div>
                        </div>

                        <div class="spm-progress" aria-hidden="true">
                            <div class="spm-progress__bar" style="width: {{ $pct }}%"></div>
                        </div>

                        <div class="spm-table-shell">
                            <table class="spm-table">
                                <thead>
                                    <tr>
                                        <th class="spm-col-group">المجموعة</th>
                                        <th class="spm-col-all">الكل</th>
                                        @foreach ($actionLabels as $actionKey => $actionLabel)
                                            <th class="spm-col-action">{{ $actionLabel }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                @foreach ($sections as $block)
                                    @php $section = $block['section']; @endphp
                                    <tbody class="spm-section" data-section="{{ $section['key'] }}">
                                        <tr class="spm-section-row">
                                            <td colspan="6">
                                                <div class="spm-section-head">
                                                    <span class="spm-section-head__title">{{ $section['label'] }}</span>
                                                    <span class="spm-section-head__desc">{{ $section['description'] }}</span>
                                                </div>
                                            </td>
                                        </tr>
                                        @foreach ($block['groups'] as $group)
                                            @php $row = $matrix[$group['key']] ?? []; @endphp
                                            <tr class="spm-row" wire:key="spm-row-{{ $group['key'] }}">
                                                <td class="spm-col-group">
                                                    <span class="spm-group-label">{{ $group['label'] }}</span>
                                                </td>
                                                <td class="spm-col-all">
                                                    <button
                                                        type="button"
                                                        class="spm-check spm-check--all {{ ($row['all'] ?? false) ? 'is-on' : '' }}"
                                                        wire:click.prevent="toggleGroup('{{ $group['key'] }}')"
                                                        title="تبديل كل صلاحيات المجموعة"
                                                        aria-pressed="{{ ($row['all'] ?? false) ? 'true' : 'false' }}"
                                                    >
                                                        @if ($row['all'] ?? false)
                                                            <x-heroicon-s-check class="h-3.5 w-3.5" />
                                                        @endif
                                                    </button>
                                                </td>
                                                @foreach ($actionLabels as $actionKey => $actionLabel)
                                                    @php $enabled = $row[$actionKey.'_enabled'] ?? false; @endphp
                                                    <td class="spm-col-action">
                                                        @if ($enabled)
                                                            <button
                                                                type="button"
                                                                class="spm-check {{ ($row[$actionKey] ?? false) ? 'is-on' : '' }}"
                                                                wire:click.prevent="toggleAction('{{ $group['key'] }}', '{{ $actionKey }}')"
                                                                title="{{ $actionLabel }} — {{ $group['label'] }}"
                                                                aria-pressed="{{ ($row[$actionKey] ?? false) ? 'true' : 'false' }}"
                                                            >
                                                                @if ($row[$actionKey] ?? false)
                                                                    <x-heroicon-s-check class="h-3.5 w-3.5" />
                                                                @endif
                                                            </button>
                                                        @else
                                                            <span class="spm-na" title="غير متاح لهذه المجموعة">—</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
