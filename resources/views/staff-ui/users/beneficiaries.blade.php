@php
    use App\Enums\AccountStatus;
    use App\Support\Privacy\SensitiveContactMasker;
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="المستخدمين" :dashboard-active="false" active-nav="users">
    @if (session('status'))
        <div hidden data-sui-flash data-tone="success" data-title="تم" data-body="{{ session('status') }}"></div>
    @endif
    @foreach ($errors->all() as $error)
        <div hidden data-sui-flash data-tone="danger" data-title="تعذر التصدير" data-body="{{ $error }}"></div>
    @endforeach

    <header class="sui-page-head sui-staff-head">
        <h1>المستخدمين</h1>
        @if ($canExport)
            <x-staff-ui.button type="button" variant="secondary" size="sm" icon="download" data-sui-open-modal="export-beneficiaries">تصدير Excel</x-staff-ui.button>
        @endif
    </header>

    <x-staff-ui.tabs group="users" :tabs="[
        ['id' => 'beneficiaries', 'label' => 'المستفيدين', 'active' => true, 'count' => $beneficiaries->total(), 'href' => route('staff-ui.users.index')],
        $canViewStaff
            ? ['id' => 'staff', 'label' => 'الموظفين', 'href' => route('staff-ui.users.staff.index'), 'count' => $staffCount]
            : ['id' => 'staff', 'label' => 'الموظفين', 'disabled' => true],
    ]" />

    <x-staff-ui.data-table
        server
        :class="$beneficiaries->isEmpty() ? 'is-empty' : ''"
        :columns="[
            ['label' => 'الاسم', 'key' => 'name'],
            ['label' => 'البريد', 'key' => 'email'],
            ['label' => 'الجوال', 'key' => 'phone'],
            ['label' => 'الحالة', 'key' => 'status'],
            ['label' => 'اكتمال الملف', 'key' => 'profile'],
        ]"
    >
        <x-slot:filters>
            <form class="sui-table__filters" method="GET" action="{{ route('staff-ui.users.index') }}">
                <x-staff-ui.input name="q" label="بحث" placeholder="الاسم أو البريد أو الجوال" :value="$search" />
                <x-staff-ui.select
                    name="status"
                    label="الحالة"
                    :selected="$status"
                    :options="['' => 'الكل', 'active' => 'نشط', 'inactive' => 'معطّل']"
                />
                <x-staff-ui.select
                    name="profile"
                    label="اكتمال الملف"
                    :selected="$completeness"
                    :options="['' => 'الكل', 'complete' => 'مكتمل', 'incomplete' => 'غير مكتمل']"
                />
                <x-staff-ui.select
                    name="identity_category"
                    label="الجنسية"
                    :selected="$identityCategory"
                    :options="array_filter([
                        '' => 'الكل',
                        'saudi' => 'سعودي',
                        'resident' => 'مقيم',
                        'invalid' => ($canFilterInvalidIdentity ?? false) ? 'رقم هوية غير صالح' : null,
                    ], fn ($label) => $label !== null)"
                />
                <div class="sui-table__filters-actions">
                    <x-staff-ui.button type="submit" variant="secondary" size="sm">تطبيق</x-staff-ui.button>
                    @if ($search !== '' || $status !== '' || $completeness !== '' || $identityCategory !== '')
                        <x-staff-ui.button variant="ghost" size="sm" :href="route('staff-ui.users.index')">مسح</x-staff-ui.button>
                    @endif
                </div>
            </form>
        </x-slot:filters>

        @foreach ($beneficiaries as $beneficiary)
            @php
                $email = $canViewContact
                    ? $beneficiary->email
                    : SensitiveContactMasker::maskEmail($beneficiary->email);
                $phone = filled($beneficiary->phone)
                    ? ($canViewContact ? $beneficiary->phone : SensitiveContactMasker::maskPhone($beneficiary->phone))
                    : '—';
                $active = (bool) $beneficiary->is_active && $beneficiary->account_status === AccountStatus::Active;
                $complete = $beneficiary->hasCompletedRequiredIdentityData();
            @endphp
            <tr>
                <td><a href="{{ route('staff-ui.users.show', $beneficiary) }}">{{ $beneficiary->fullName() }}</a></td>
                <td>{{ $email }}</td>
                <td>{{ $phone }}</td>
                <td>
                    @if ($active)
                        <x-staff-ui.status tone="success">نشط</x-staff-ui.status>
                    @else
                        <x-staff-ui.status tone="danger">معطّل</x-staff-ui.status>
                    @endif
                </td>
                <td>
                    @if ($complete)
                        <x-staff-ui.status tone="success">مكتمل</x-staff-ui.status>
                    @else
                        <x-staff-ui.status tone="warning">غير مكتمل</x-staff-ui.status>
                    @endif
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <div class="sui-pager">
                <span class="sui-pager__label">{{ $beneficiaries->firstItem() ?? 0 }}–{{ $beneficiaries->lastItem() ?? 0 }} من {{ $beneficiaries->total() }}</span>
                @foreach ([
                    ['url' => $beneficiaries->url(1), 'enabled' => ! $beneficiaries->onFirstPage(), 'icon' => 'chevrons-right', 'label' => 'الصفحة الأولى'],
                    ['url' => $beneficiaries->previousPageUrl(), 'enabled' => ! $beneficiaries->onFirstPage(), 'icon' => 'chevron-right', 'label' => 'السابق'],
                    ['url' => $beneficiaries->nextPageUrl(), 'enabled' => $beneficiaries->hasMorePages(), 'icon' => 'chevron-left', 'label' => 'التالي'],
                    ['url' => $beneficiaries->url($beneficiaries->lastPage()), 'enabled' => $beneficiaries->hasMorePages(), 'icon' => 'chevrons-left', 'label' => 'الصفحة الأخيرة'],
                ] as $pager)
                    @if ($pager['enabled'])
                        <a class="sui-icon-btn" href="{{ $pager['url'] }}" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></a>
                    @else
                        <span class="sui-icon-btn" aria-disabled="true" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></span>
                    @endif
                @endforeach
            </div>
        </x-slot:footer>
    </x-staff-ui.data-table>

    @if ($canExport)
        <x-staff-ui.modal name="export-beneficiaries" title="تصدير ملفات المستفيدين" :open="$errors->has('columns')">
            <form method="POST" action="{{ route('staff-ui.users.export') }}">
                @csrf
                <input type="hidden" name="q" value="{{ $search }}">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="hidden" name="profile" value="{{ $completeness }}">
                <input type="hidden" name="identity_category" value="{{ $identityCategory }}">
                <p>{{ $exportCountSentence }}</p>
                <div class="sui-export-columns">
                    @foreach ($exportColumns as $key => $label)
                        <x-staff-ui.checkbox
                            name="columns[]"
                            :value="$key"
                            :label="$label"
                            :checked="in_array($key, $exportDefaults, true)"
                        />
                    @endforeach
                </div>
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" size="sm">تصدير</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif
</x-staff-ui.layout>
