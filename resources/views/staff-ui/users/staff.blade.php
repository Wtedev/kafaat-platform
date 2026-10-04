@php
    use App\Services\Rbac\RbacCatalog;
    use App\Services\StaffUi\StaffInvitationService;
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="المستخدمين" :dashboard-active="false" active-nav="users">
    <header class="sui-page-head sui-page-head--row">
        <h1>المستخدمين</h1>
        @if ($canInvite)
            <x-staff-ui.button type="button" variant="primary" size="sm" icon="user-plus" data-sui-open-modal="invite-staff">دعوة موظف</x-staff-ui.button>
        @endif
    </header>

    @if (session('status'))
        <p class="sui-alert" role="status">{{ session('status') }}</p>
    @endif

    <x-staff-ui.tabs group="users" :tabs="[
        ['id' => 'beneficiaries', 'label' => 'المستفيدين', 'href' => route('staff-ui.users.index')],
        ['id' => 'staff', 'label' => 'الموظفين', 'active' => true, 'count' => $directory->total(), 'href' => route('staff-ui.users.staff.index')],
    ]" />

    <x-staff-ui.data-table
        server
        :class="$directory->isEmpty() ? 'is-empty' : ''"
        :columns="[
            ['label' => 'الاسم', 'key' => 'name'],
            ['label' => 'البريد', 'key' => 'email'],
            ['label' => 'الدور', 'key' => 'role'],
            ['label' => 'الحالة', 'key' => 'status'],
            ['label' => 'آخر دخول', 'key' => 'last_login'],
            ['label' => 'إجراءات', 'key' => 'actions'],
        ]"
    >
        <x-slot:filters>
            <form class="sui-table__filters" method="GET" action="{{ route('staff-ui.users.staff.index') }}">
                <x-staff-ui.input name="q" label="بحث" placeholder="الاسم أو البريد" :value="$search" />
                <x-staff-ui.select
                    name="role"
                    label="الدور"
                    :selected="$role"
                    :options="['' => 'الكل', 'admin' => RbacCatalog::roleArabicLabel('admin'), 'staff' => RbacCatalog::roleArabicLabel('staff')]"
                />
                <x-staff-ui.select
                    name="status"
                    label="الحالة"
                    :selected="$status"
                    :options="['' => 'الكل', 'active' => 'نشط', 'inactive' => 'معطّل', 'invited' => 'مدعو']"
                />
                <div class="sui-table__filters-actions">
                    <x-staff-ui.button type="submit" variant="secondary" size="sm">تطبيق</x-staff-ui.button>
                    @if ($search !== '' || $role !== '' || $status !== '')
                        <x-staff-ui.button variant="ghost" size="sm" :href="route('staff-ui.users.staff.index')">مسح</x-staff-ui.button>
                    @endif
                </div>
            </form>
        </x-slot:filters>

        @foreach ($directory as $member)
            @php
                $pending = app(StaffInvitationService::class)->isPending($member);
                $isSelf = auth()->id() === $member->id;
            @endphp
            <tr>
                <td>{{ $member->name }}</td>
                <td>{{ $member->email }}</td>
                <td>{{ $member->filamentStaffRoleLabelsAr() }}</td>
                <td>
                    @if ($member->is_active)
                        <x-staff-ui.status tone="success">نشط</x-staff-ui.status>
                    @elseif ($pending)
                        <x-staff-ui.status tone="warning">مدعو</x-staff-ui.status>
                    @else
                        <x-staff-ui.status tone="danger">معطّل</x-staff-ui.status>
                    @endif
                </td>
                <td>{{ $member->last_login_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</td>
                <td>
                    <div class="sui-table__filters-actions">
                        @if ($canChangeRole && ! $isSelf)
                            <form method="POST" action="{{ route('staff-ui.users.staff.role', $member) }}">
                                @csrf
                                <x-staff-ui.select :id="'role-'.$member->id" name="role" label="الدور" :selected="$member->isAdmin() ? 'admin' : 'staff'" :options="$roles" />
                                <x-staff-ui.button type="submit" variant="secondary" size="sm">تغيير الدور</x-staff-ui.button>
                            </form>
                        @endif
                        @if ($canActivate && ! $isSelf && ! $pending && ! $member->isProtectedAdminUser())
                            <form method="POST" action="{{ route('staff-ui.users.staff.activation', $member) }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $member->is_active ? 'deactivate' : 'activate' }}">
                                <x-staff-ui.button type="submit" variant="{{ $member->is_active ? 'danger' : 'secondary' }}" size="sm">
                                    {{ $member->is_active ? 'تعطيل' : 'تفعيل' }}
                                </x-staff-ui.button>
                            </form>
                        @endif
                        @if ($canInvite && $pending)
                            <form method="POST" action="{{ route('staff-ui.users.staff.invitation', $member) }}">
                                @csrf
                                <input type="hidden" name="action" value="resend">
                                <x-staff-ui.button type="submit" variant="secondary" size="sm">إعادة الإرسال</x-staff-ui.button>
                            </form>
                            <form method="POST" action="{{ route('staff-ui.users.staff.invitation', $member) }}">
                                @csrf
                                <input type="hidden" name="action" value="cancel">
                                <x-staff-ui.button type="submit" variant="ghost" size="sm">إلغاء الدعوة</x-staff-ui.button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <div class="sui-pager">
                <span class="sui-pager__label">{{ $directory->firstItem() ?? 0 }}–{{ $directory->lastItem() ?? 0 }} من {{ $directory->total() }}</span>
                @foreach ([
                    ['url' => $directory->url(1), 'enabled' => ! $directory->onFirstPage(), 'icon' => 'chevrons-right', 'label' => 'الصفحة الأولى'],
                    ['url' => $directory->previousPageUrl(), 'enabled' => ! $directory->onFirstPage(), 'icon' => 'chevron-right', 'label' => 'السابق'],
                    ['url' => $directory->nextPageUrl(), 'enabled' => $directory->hasMorePages(), 'icon' => 'chevron-left', 'label' => 'التالي'],
                    ['url' => $directory->url($directory->lastPage()), 'enabled' => $directory->hasMorePages(), 'icon' => 'chevrons-left', 'label' => 'الصفحة الأخيرة'],
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

    @if ($canInvite)
        <x-staff-ui.modal name="invite-staff" title="دعوة موظف" :open="$errors->any()">
            <form method="POST" action="{{ route('staff-ui.users.staff.store') }}">
                @csrf
                <x-staff-ui.input name="name" label="الاسم" :value="old('name')" :error="$errors->first('name')" required />
                <x-staff-ui.input name="email" type="email" label="البريد الإلكتروني" :value="old('email')" :error="$errors->first('email')" required />
                <x-staff-ui.select id="invite-role" name="role" label="الدور" :selected="old('role', 'staff')" :options="$roles" :error="$errors->first('role')" />
                <div class="sui-modal__actions">
                    <x-staff-ui.button type="submit" variant="primary" size="sm">إرسال الدعوة</x-staff-ui.button>
                    <x-staff-ui.button type="button" variant="ghost" size="sm" data-sui-modal-close>إلغاء</x-staff-ui.button>
                </div>
            </form>
        </x-staff-ui.modal>
    @endif
</x-staff-ui.layout>
