@extends('layouts.portal')
@section('title', 'الخصوصية والبيانات')

@section('content')
<h1 class="mb-2 text-2xl font-bold text-gray-900">الخصوصية والبيانات</h1>
<p class="mb-8 text-sm text-gray-600">اطّلع على بياناتك وإقراراتك وطلبات الخصوصية، وقدّم طلبات الوصول أو التصحيح أو حذف الحساب.</p>

<div class="max-w-4xl space-y-8">
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-gray-900">بيانات الحساب</h2>
        <dl class="grid gap-4 sm:grid-cols-2 text-sm">
            <div><dt class="text-gray-500">الاسم الرباعي</dt><dd class="font-medium text-gray-900">{{ $privacy->account['full_name'] }}</dd></div>
            <div><dt class="text-gray-500">البريد</dt><dd class="font-medium text-gray-900" dir="ltr">{{ $privacy->account['email'] }}</dd></div>
            <div><dt class="text-gray-500">الجوال</dt><dd class="font-medium text-gray-900" dir="ltr">{{ $privacy->account['phone'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">تاريخ الميلاد</dt><dd class="font-medium text-gray-900">{{ $privacy->account['birth_date'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">نوع الهوية</dt><dd class="font-medium text-gray-900">{{ $privacy->account['identity_type'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">رقم الهوية</dt><dd class="font-medium text-gray-900" dir="ltr">{{ $privacy->account['identity_masked'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">تاريخ إنشاء الحساب</dt><dd class="font-medium text-gray-900">{{ $privacy->account['created_at'] }}</dd></div>
            <div><dt class="text-gray-500">آخر تحديث</dt><dd class="font-medium text-gray-900">{{ $privacy->account['updated_at'] }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-gray-500">حالة الحساب</dt><dd class="font-medium text-gray-900">{{ $privacy->account['account_status'] }}</dd></div>
        </dl>
    </section>

    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-gray-900">سياسة الخصوصية</h2>
        <dl class="space-y-3 text-sm">
            <div><span class="text-gray-500">الإصدار الفعال:</span> <span class="font-medium">{{ $privacy->policy['active_version'] ?? '—' }}</span>
                @if ($privacy->policy['active_url'])<a href="{{ $privacy->policy['active_url'] }}" class="ms-2 text-[#335483] hover:underline">عرض السياسة</a>@endif
            </div>
            <div><span class="text-gray-500">آخر إقرار:</span> <span class="font-medium">{{ $privacy->policy['acknowledged_version'] ?? '—' }}</span>
                @if ($privacy->policy['acknowledged_version_url'])<a href="{{ $privacy->policy['acknowledged_version_url'] }}" class="ms-2 text-[#335483] hover:underline">عرض الإصدار</a>@endif
            </div>
            <div><span class="text-gray-500">تاريخ الإقرار:</span> <span class="font-medium">{{ $privacy->policy['acknowledged_at'] ?? '—' }}</span></div>
            @if ($privacy->policy['needs_reacknowledgement'])
            <p class="rounded-xl bg-amber-50 px-4 py-3 text-amber-900">يلزم إعادة الإقرار بسياسة الخصوصية المحدّثة.</p>
            @endif
        </dl>
    </section>

    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-gray-900">قاعدة المرشحين</h2>
        <p class="mb-2 text-sm text-gray-700">الحالة: <strong>{{ $privacy->candidatePool['status_label'] }}</strong></p>
        @if ($privacy->candidatePool['last_decision_at'])
        <p class="mb-4 text-sm text-gray-600">آخر قرار: {{ $privacy->candidatePool['last_decision_at'] }}</p>
        @endif
        <a href="{{ $privacy->candidatePool['settings_url'] }}" class="inline-flex rounded-xl bg-[#e9eff6] px-4 py-2 text-sm font-semibold text-[#335483]">إدارة موافقة قاعدة المرشحين</a>
    </section>

    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-gray-900">السيرة الذاتية</h2>
        @if ($privacy->cv)
        <dl class="mb-4 grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">تاريخ الرفع</dt><dd>{{ $privacy->cv['uploaded_at'] }}</dd></div>
            <div><dt class="text-gray-500">النوع</dt><dd>{{ $privacy->cv['mime'] }}</dd></div>
            <div><dt class="text-gray-500">الحجم</dt><dd>{{ $privacy->cv['size_label'] }}</dd></div>
            <div><dt class="text-gray-500">آخر تحديث</dt><dd>{{ $privacy->cv['updated_at'] }}</dd></div>
        </dl>
        <div class="flex flex-wrap gap-3">
            <a href="{{ $privacy->cv['download_url'] }}" class="rounded-xl bg-[#335483] px-4 py-2 text-sm font-semibold text-white">تنزيل آمن</a>
            <form method="POST" action="{{ $privacy->cv['delete_url'] }}" onsubmit="return confirm('هل تريد حذف السيرة الذاتية؟');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700">حذف آمن</button>
            </form>
        </div>
        @else
        <p class="text-sm text-gray-600">لا توجد سيرة ذاتية مرفوعة. يمكنك رفعها من <a href="{{ route('portal.competency') }}" class="text-[#335483] hover:underline">صفحة الكفاءة</a>.</p>
        @endif
    </section>

    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-gray-900">طلبات الخصوصية</h2>
        @if ($privacy->requests === [])
        <p class="text-sm text-gray-600">لا توجد طلبات سابقة.</p>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b text-gray-500">
                    <tr>
                        <th class="py-2 text-right font-medium">المرجع</th>
                        <th class="py-2 text-right font-medium">النوع</th>
                        <th class="py-2 text-right font-medium">الحالة</th>
                        <th class="py-2 text-right font-medium">التقديم</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($privacy->requests as $req)
                    <tr>
                        <td class="py-3 font-mono text-xs" dir="ltr">{{ $req['uuid'] }}</td>
                        <td class="py-3">{{ $req['type'] }}</td>
                        <td class="py-3">{{ $req['status'] }}</td>
                        <td class="py-3">{{ $req['submitted_at'] }}</td>
                    </tr>
                    @if ($req['user_message'])
                    <tr><td colspan="4" class="pb-3 text-xs text-gray-600">{{ $req['user_message'] }}</td></tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </section>

    @if ($privacy->canSubmitRequests)
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-gray-900">الإجراءات</h2>
        <div class="space-y-6">
            <form method="POST" action="{{ route('portal.privacy.requests.access') }}" class="rounded-xl border border-gray-100 p-4">
                @csrf
                <h3 class="mb-2 font-semibold text-gray-900">طلب الوصول إلى بياناتي</h3>
                <p class="mb-4 text-sm text-gray-600">طلب رسمي لمعرفة فئات البيانات التي تحتفظ بها المنصة عنك (وليس ملف تصدير).</p>
                <button type="submit" class="rounded-xl bg-[#335483] px-5 py-2.5 text-sm font-semibold text-white">تقديم طلب الوصول</button>
            </form>

            <form method="POST" action="{{ route('portal.privacy.requests.correction') }}" class="rounded-xl border border-gray-100 p-4">
                @csrf
                <h3 class="mb-2 font-semibold text-gray-900">طلب تصحيح بياناتي</h3>
                <p class="mb-4 text-sm text-gray-600">للحقول التي لا يمكن تعديلها مباشرة من الملف الشخصي.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium">نوع الحقل</label>
                        <select name="field_code" required class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            @foreach (\App\Enums\PrivacyCorrectionFieldCode::cases() as $field)
                            <option value="{{ $field->value }}">{{ $field->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium">سبب التصحيح</label>
                        <textarea name="reason" required rows="3" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">الاسم الأول (عند تصحيح الاسم)</label>
                        <input type="text" name="first_name" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">اسم الأب</label>
                        <input type="text" name="father_name" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">اسم الجد</label>
                        <input type="text" name="grandfather_name" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">اسم العائلة</label>
                        <input type="text" name="family_name" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">تاريخ الميلاد الجديد</label>
                        <input type="date" name="birth_date" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">البريد الجديد</label>
                        <input type="email" name="email" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" dir="ltr" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">نوع الهوية</label>
                        <select name="identity_type" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            @foreach (\App\Enums\IdentityType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">رقم الهوية الجديد</label>
                        <input type="text" name="identity_number" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" dir="ltr" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium">كلمة المرور (مطلوبة للحقول الحساسة)</label>
                        <input type="password" name="password" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                </div>
                <button type="submit" class="mt-4 rounded-xl bg-[#335483] px-5 py-2.5 text-sm font-semibold text-white">تقديم طلب التصحيح</button>
            </form>

            <form method="POST" action="{{ route('portal.account-deletion.store') }}" class="rounded-xl border border-red-100 bg-red-50/50 p-4" onsubmit="return confirm('هل أنت متأكد من طلب حذف حسابك؟');">
                @csrf
                <h3 class="mb-2 font-semibold text-red-900">طلب حذف الحساب</h3>
                <p class="mb-4 text-sm text-red-800">سيُراجع الطلب ويُنفَّذ التعمية وليس الحذف الكامل للسجل.</p>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-red-900">كلمة المرور</label>
                    <input type="password" name="password" required class="w-full max-w-md rounded-xl border border-red-200 px-3 py-2 text-sm" />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-red-900">سبب اختياري</label>
                    <textarea name="reason" rows="2" maxlength="500" class="w-full rounded-xl border border-red-200 px-3 py-2 text-sm"></textarea>
                </div>
                <button type="submit" class="rounded-xl bg-red-700 px-5 py-2.5 text-sm font-semibold text-white">تقديم طلب الحذف</button>
            </form>
        </div>
    </section>
    @endif
</div>
@endsection
