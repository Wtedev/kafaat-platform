<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 dark:bg-white/[0.03]">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">مصفوفة صلاحيات الموظفين</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                هذه الصفحة متاحة لحساب الأدمن فقط. اختر موظفاً من التبويبات ثم فعّل/ألغِ صلاحيات CRUD لكل مجموعة، ثم احفظ.
            </p>
        </div>

        @php
            $staffUsers = $this->staffUsers();
            $groups = $this->groups();
            $actionLabels = $this->actionLabels();
        @endphp

        @if ($staffUsers->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                لا يوجد موظفون نشطون بعد. أضف مستخدماً بنوع «موظف» من قائمة المستخدمين، وسيحصل تلقائياً على كل صلاحيات المصفوفة.
            </div>
        @else
            <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-3 dark:border-white/10">
                @foreach ($staffUsers as $staff)
                    <button
                        type="button"
                        wire:click="selectStaff({{ $staff->id }})"
                        @class([
                            'rounded-xl px-4 py-2 text-sm font-medium transition',
                            'bg-[#335483] text-white shadow' => $activeStaffId === $staff->id,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10' => $activeStaffId !== $staff->id,
                        ])
                    >
                        {{ $staff->name }}
                    </button>
                @endforeach
            </div>

            @if ($activeStaffId)
                @php $active = $staffUsers->firstWhere('id', $activeStaffId); @endphp
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $active?->name }}</p>
                        <p class="text-xs text-gray-500" dir="ltr">{{ $active?->email }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="grantAll" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                            تحديد الكل
                        </button>
                        <button type="button" wire:click="clearAll" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                            إلغاء الكل
                        </button>
                        <button type="button" wire:click="save" class="rounded-lg bg-[#335483] px-4 py-1.5 text-xs font-semibold text-white hover:opacity-95">
                            حفظ الصلاحيات
                        </button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr class="text-right">
                                <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-200">المجموعة</th>
                                <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200">الكل</th>
                                @foreach ($actionLabels as $actionKey => $actionLabel)
                                    <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-200">{{ $actionLabel }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($groups as $group)
                                @php $row = $matrix[$group['key']] ?? []; @endphp
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.03]">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $group['label'] }}</td>
                                    <td class="px-3 py-3 text-center">
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border-gray-300 text-[#335483] focus:ring-[#335483]"
                                            @checked($row['all'] ?? false)
                                            wire:click.prevent="toggleGroup('{{ $group['key'] }}')"
                                        >
                                    </td>
                                    @foreach ($actionLabels as $actionKey => $actionLabel)
                                        @php $enabled = $row[$actionKey.'_enabled'] ?? false; @endphp
                                        <td class="px-3 py-3 text-center">
                                            @if ($enabled)
                                                <input
                                                    type="checkbox"
                                                    class="size-4 rounded border-gray-300 text-[#335483] focus:ring-[#335483]"
                                                    @checked($row[$actionKey] ?? false)
                                                    wire:click.prevent="toggleAction('{{ $group['key'] }}', '{{ $actionKey }}')"
                                                >
                                            @else
                                                <span class="text-xs text-gray-300 dark:text-gray-600">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
