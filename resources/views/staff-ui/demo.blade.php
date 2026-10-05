<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="لوحة التحكم" preview>
    <x-staff-ui.announcement pill="جديد" title="واجهة الموظفين الجديدة جاهزة للمعاينة">
        <x-slot:action>
            <x-staff-ui.button variant="inverse" size="sm" href="#sui-library">استكشف المكوّنات</x-staff-ui.button>
        </x-slot:action>
    </x-staff-ui.announcement>

    <x-staff-ui.section-header title="نظرة عامة" />

    <div class="sui-kpi-grid">
        <x-staff-ui.kpi icon="users" label="المستفيدون" value="1,284" delta="+12%" />
        <x-staff-ui.kpi icon="clipboard-list" label="التسجيلات" value="326" delta="-4%" :positive="false" />
        <x-staff-ui.kpi icon="award" label="الشهادات الصادرة" value="89" delta="+41%" />
        <x-staff-ui.kpi icon="clock" label="ساعات التطوع" value="1,540" delta="+8%" />
    </div>

    <div class="sui-chart-grid">
        <x-staff-ui.chart-card
            title="التسجيلات خلال الأشهر"
            value="326"
            delta="+18 عن الفترة السابقة"
            kind="bar"
            :labels="['مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر']"
            :values="[28, 42, 36, 51, 47, 62]"
        />
        <x-staff-ui.chart-card
            title="إكمال البرامج"
            value="74%"
            delta="+6% عن الفترة السابقة"
            kind="line"
            :labels="['الأسبوع 1', 'الأسبوع 2', 'الأسبوع 3', 'الأسبوع 4']"
            :values="[48, 55, 52, 74]"
        />
    </div>

    <section class="sui-card" style="overflow: hidden;">
        <x-staff-ui.tabs
            group="programs"
            :tabs="[
                ['id' => 'all', 'label' => 'الكل', 'count' => 8, 'active' => true],
                ['id' => 'done', 'label' => 'مكتمل', 'count' => 2],
                ['id' => 'progress', 'label' => 'قيد التنفيذ', 'count' => 3],
                ['id' => 'pending', 'label' => 'بانتظار الاعتماد', 'count' => 2],
                ['id' => 'cancelled', 'label' => 'ملغي', 'count' => 1],
            ]"
        />
        <x-staff-ui.data-table
            group="programs"
            :per-page="5"
            :columns="[
                ['key' => 'Name', 'label' => 'البرنامج', 'sortable' => true],
                ['key' => 'Owner', 'label' => 'المسؤول', 'sortable' => true],
                ['key' => 'Date', 'label' => 'التاريخ', 'sortable' => true],
                ['key' => 'Status', 'label' => 'الحالة', 'sortable' => false],
                ['key' => 'Count', 'label' => 'المسجلون', 'sortable' => true],
            ]"
        >
            @foreach ([
                ['name' => 'تحليل البيانات', 'owner' => 'سارة القحطاني', 'date' => '2026-10-12', 'filter' => 'done', 'tone' => 'success', 'status' => 'مكتمل', 'count' => 42],
                ['name' => 'مهارات العرض', 'owner' => 'فهد الدوسري', 'date' => '2026-10-18', 'filter' => 'progress', 'tone' => 'info', 'status' => 'قيد التنفيذ', 'count' => 27],
                ['name' => 'قيادة الفرق', 'owner' => 'نورة العتيبي', 'date' => '2026-09-30', 'filter' => 'pending', 'tone' => 'warning', 'status' => 'بانتظار الاعتماد', 'count' => 15],
                ['name' => 'خدمة المستفيد', 'owner' => 'خالد الشمري', 'date' => '2026-08-21', 'filter' => 'cancelled', 'tone' => 'danger', 'status' => 'ملغي', 'count' => 8],
                ['name' => 'التخطيط المالي', 'owner' => 'هند الزهراني', 'date' => '2026-10-02', 'filter' => 'progress', 'tone' => 'info', 'status' => 'قيد التنفيذ', 'count' => 33],
                ['name' => 'كتابة التقارير', 'owner' => 'ماجد الحربي', 'date' => '2026-07-14', 'filter' => 'done', 'tone' => 'success', 'status' => 'مكتمل', 'count' => 51],
                ['name' => 'إدارة الوقت', 'owner' => 'ريم السبيعي', 'date' => '2026-10-25', 'filter' => 'pending', 'tone' => 'warning', 'status' => 'بانتظار الاعتماد', 'count' => 19],
                ['name' => 'العمل الجماعي', 'owner' => 'عبدالله المطيري', 'date' => '2026-09-09', 'filter' => 'progress', 'tone' => 'info', 'status' => 'قيد التنفيذ', 'count' => 24],
            ] as $row)
                <tr
                    data-sui-row
                    data-filter="{{ $row['filter'] }}"
                    data-search="{{ $row['name'] }} {{ $row['owner'] }} {{ $row['status'] }}"
                    data-sort-name="{{ $row['name'] }}"
                    data-sort-owner="{{ $row['owner'] }}"
                    data-sort-date="{{ $row['date'] }}"
                    data-sort-count="{{ $row['count'] }}"
                >
                    <td><input class="sui-check" type="checkbox" data-sui-row-check aria-label="تحديد {{ $row['name'] }}"></td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['owner'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td><x-staff-ui.status :tone="$row['tone']">{{ $row['status'] }}</x-staff-ui.status></td>
                    <td>{{ $row['count'] }}</td>
                    <td>
                        <x-staff-ui.dropdown align="end">
                            <x-slot:trigger>
                                <button type="button" class="sui-icon-btn" aria-label="إجراءات {{ $row['name'] }}">
                                    <i data-lucide="ellipsis" class="sui-icon"></i>
                                </button>
                            </x-slot:trigger>
                            <button type="button" class="sui-menu-item" data-sui-open-modal="row-details">عرض</button>
                            <button type="button" class="sui-menu-item sui-menu-item--danger" data-sui-open-modal="confirm-delete">حذف</button>
                        </x-staff-ui.dropdown>
                    </td>
                </tr>
            @endforeach
        </x-staff-ui.data-table>
    </section>

    <section id="sui-library" class="sui-library">
        <x-staff-ui.section-header title="مكتبة المكوّنات" />

        <div class="sui-card sui-row">
            <x-staff-ui.button size="sm">أساسي</x-staff-ui.button>
            <x-staff-ui.button size="md">أساسي متوسط</x-staff-ui.button>
            <x-staff-ui.button variant="secondary" size="sm">ثانوي</x-staff-ui.button>
            <x-staff-ui.button variant="ghost" size="sm">شفاف</x-staff-ui.button>
            <x-staff-ui.button variant="danger" size="sm">خطر</x-staff-ui.button>
            <x-staff-ui.button variant="secondary" size="sm" icon="download">مع أيقونة</x-staff-ui.button>
            <x-staff-ui.button variant="secondary" size="sm" icon="plus" icon-only aria-label="إضافة" />
            <x-staff-ui.button size="sm" type="button" data-sui-open-modal="sample-modal">نافذة</x-staff-ui.button>
            <x-staff-ui.button variant="danger" size="sm" type="button" data-sui-open-modal="confirm-delete">حذف</x-staff-ui.button>
            <x-staff-ui.button variant="secondary" size="sm" type="button" data-sui-toast data-tone="success" data-title="تم الحفظ" data-body="رسالة تجريبية.">تنبيه</x-staff-ui.button>
            <x-staff-ui.button variant="ghost" size="sm" type="button" data-sui-loading>حالة التحميل</x-staff-ui.button>
        </div>

        <div class="sui-card sui-row">
            <x-staff-ui.status tone="success">مكتمل</x-staff-ui.status>
            <x-staff-ui.status tone="info">قيد التنفيذ</x-staff-ui.status>
            <x-staff-ui.status tone="warning">بانتظار الاعتماد</x-staff-ui.status>
            <x-staff-ui.status tone="danger">ملغي</x-staff-ui.status>
            <x-staff-ui.count>12</x-staff-ui.count>
            <x-staff-ui.count tone="primary">3</x-staff-ui.count>
            <x-staff-ui.avatar name="نورة العتيبي" />
        </div>

        <form class="sui-card sui-form-grid" action="#" method="get" onsubmit="return false;">
            <x-staff-ui.input label="اسم البرنامج" name="demo_name" placeholder="اكتب الاسم" hint="يظهر للمستخدمين داخل لوحة الموظفين." />
            <x-staff-ui.input label="البريد" name="demo_email" type="email" value="name@example.com" error="صيغة البريد غير مكتملة." />
            <x-staff-ui.select label="الحالة" name="demo_status" :options="['done' => 'مكتمل', 'progress' => 'قيد التنفيذ', 'pending' => 'بانتظار الاعتماد']" hint="قائمة تجريبية." />
            <x-staff-ui.date label="تاريخ البداية" name="demo_date" />
            <div class="sui-span-2">
                <x-staff-ui.textarea label="الوصف" name="demo_about" placeholder="نبذة قصيرة" hint="حتى بضعة أسطر." />
            </div>
            <div class="sui-span-2">
                <x-staff-ui.file label="مرفق" name="demo_file" hint="PDF أو صورة." />
            </div>
            <div class="sui-span-2 sui-row" style="padding: 0;">
                <x-staff-ui.toggle label="نشر للمعاينة" name="demo_publish" checked />
                <x-staff-ui.checkbox label="أوافق على مراجعة المحتوى" name="demo_agree" />
            </div>
        </form>
    </section>

    <x-staff-ui.modal name="sample-modal" title="نافذة المعاينة">
        هذا مثال على النافذة. لا يحفظ أي بيانات.
        <x-slot:actions>
            <x-staff-ui.button variant="secondary" size="sm" type="button" data-sui-modal-close>إغلاق</x-staff-ui.button>
            <x-staff-ui.button size="sm" type="button" data-sui-modal-close data-sui-toast data-tone="success" data-title="تم" data-body="أُغلقت النافذة.">متابعة</x-staff-ui.button>
        </x-slot:actions>
    </x-staff-ui.modal>

    <x-staff-ui.modal name="row-details" title="تفاصيل السجل">
        بيانات هذا الصف تجريبية لعرض شكل الجدول.
        <x-slot:actions>
            <x-staff-ui.button variant="secondary" size="sm" type="button" data-sui-modal-close>إغلاق</x-staff-ui.button>
        </x-slot:actions>
    </x-staff-ui.modal>

    <x-staff-ui.confirm-delete />
</x-staff-ui.layout>
