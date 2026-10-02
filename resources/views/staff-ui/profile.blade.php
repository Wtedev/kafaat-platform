<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="الملف الشخصي" :dashboard-active="false">
    <header class="sui-page-head">
        <h1>الملف الشخصي</h1>
    </header>

    @if (session('status'))
        <p class="sui-alert" role="status">{{ session('status') }}</p>
    @endif

    <section class="sui-card">
        <div class="sui-card__head">
            <h2>معلومات الحساب</h2>
            <p>الاسم ورقم الجوال والصورة. لا يُغيّر البريد أو كلمة المرور من زر «حفظ».</p>
        </div>
        <form class="sui-form-grid" method="POST" action="{{ route('staff-ui.profile.update') }}" enctype="multipart/form-data">
            @csrf
            <div class="sui-span-2">
                <p class="sui-field__label">الدور</p>
                <p>{{ $user->filamentStaffRoleLabelsAr() }}</p>
            </div>
            <x-staff-ui.input
                class="sui-span-2"
                label="الاسم"
                name="name"
                :value="old('name', $user->name)"
                maxlength="255"
                required
                :error="$errors->first('name')"
            />
            <x-staff-ui.input
                class="sui-span-2"
                label="رقم الجوال"
                name="phone"
                type="tel"
                :value="old('phone', $user->phone)"
                maxlength="50"
                hint="اختياري. الصيغ المقبولة: 05XXXXXXXX أو +9665XXXXXXXX"
                :error="$errors->first('phone')"
            />
            <div class="sui-span-2">
                @if ($photoUrl)
                    <img class="sui-photo" src="{{ $photoUrl }}" alt="">
                @endif
                <x-staff-ui.file
                    label="الصورة الشخصية"
                    name="staff_photo"
                    accept="image/jpeg,image/png,image/webp"
                    :error="$errors->first('staff_photo')"
                />
                @if ($user->staff_photo)
                    <x-staff-ui.checkbox label="إزالة الصورة الحالية" name="remove_staff_photo" value="1" />
                @endif
            </div>
            <div class="sui-span-2">
                <x-staff-ui.toggle
                    label="استقبال التنبيهات عبر البريد الإلكتروني"
                    name="notify_email"
                    value="1"
                    :checked="(bool) old('notify_email', $user->notify_email)"
                />
            </div>
            <div class="sui-span-2">
                <x-staff-ui.button type="submit">حفظ</x-staff-ui.button>
            </div>
        </form>
    </section>

    <section class="sui-card">
        <div class="sui-card__head">
            <h2>البريد الإلكتروني</h2>
            <p>تغيير البريد يتطلب إثبات ملكية العنوان الجديد برمز OTP.</p>
        </div>
        <div class="sui-form-grid">
            <div class="sui-span-2">
                <p class="sui-field__label">البريد الحالي</p>
                <p>{{ $user->email }}</p>
            </div>
            @if ($pendingEmail)
                <div class="sui-span-2">
                    <p class="sui-field__label">البريد الجديد (قيد التحقق)</p>
                    <p>{{ $pendingEmail }}</p>
                </div>
                <form class="sui-span-2 sui-form-grid" method="POST" action="{{ route('staff-ui.profile.email.verify') }}">
                    @csrf
                    <x-staff-ui.input
                        class="sui-span-2"
                        label="رمز التحقق"
                        name="email_otp"
                        maxlength="6"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        :error="$errors->first('email_otp')"
                    />
                    <div class="sui-span-2 sui-form-actions">
                        <x-staff-ui.button type="submit">تأكيد تغيير البريد</x-staff-ui.button>
                    </div>
                </form>
                <form method="POST" action="{{ route('staff-ui.profile.email.resend') }}">
                    @csrf
                    <x-staff-ui.button type="submit" variant="secondary">إعادة إرسال الرمز</x-staff-ui.button>
                </form>
                <form method="POST" action="{{ route('staff-ui.profile.email.cancel') }}">
                    @csrf
                    <x-staff-ui.button type="submit" variant="danger">إلغاء الطلب</x-staff-ui.button>
                </form>
            @else
                <form class="sui-span-2 sui-form-grid" method="POST" action="{{ route('staff-ui.profile.email') }}">
                    @csrf
                    <x-staff-ui.input
                        class="sui-span-2"
                        label="البريد الإلكتروني الجديد"
                        name="new_email"
                        type="email"
                        :value="old('new_email')"
                        maxlength="255"
                        autocomplete="off"
                        :error="$errors->first('new_email')"
                    />
                    <x-staff-ui.input
                        class="sui-span-2"
                        label="تأكيد البريد الإلكتروني"
                        name="new_email_confirmation"
                        type="email"
                        maxlength="255"
                        autocomplete="off"
                        :error="$errors->first('new_email_confirmation')"
                    />
                    <div class="sui-span-2">
                        <x-staff-ui.button type="submit">إرسال رمز التحقق</x-staff-ui.button>
                    </div>
                </form>
            @endif
        </div>
    </section>

    <section class="sui-card">
        <div class="sui-card__head">
            <h2>تغيير كلمة المرور</h2>
            <p>لا تُغيَّر كلمة المرور من زر «حفظ». اترك الحقول فارغة إذا لم ترغب بالتغيير.</p>
        </div>
        <form class="sui-form-grid" method="POST" action="{{ route('staff-ui.profile.password') }}">
            @csrf
            <x-staff-ui.input
                label="كلمة المرور الحالية"
                name="current_password"
                type="password"
                autocomplete="current-password"
                :error="$errors->first('current_password')"
            />
            <x-staff-ui.input
                label="كلمة المرور الجديدة"
                name="password"
                type="password"
                autocomplete="new-password"
                :error="$errors->first('password')"
            />
            <x-staff-ui.input
                class="sui-span-2"
                label="تأكيد كلمة المرور"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                :error="$errors->first('password_confirmation')"
            />
            <div class="sui-span-2">
                <x-staff-ui.button type="submit">تحديث كلمة المرور</x-staff-ui.button>
            </div>
        </form>
    </section>
</x-staff-ui.layout>
