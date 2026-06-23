@extends('layouts.public')

@section('title', 'سياسة الخصوصية — كفاءات')
@section('meta_description', 'سياسة الخصوصية لجمعية كفاءات: كيف نجمع بياناتك ونستخدمها ونحميها، وحقوقك تجاه معلوماتك الشخصية.')

@section('content')

<div class="max-w-3xl mx-auto text-right">

    <div class="mb-10">
        <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color:#1a9399">الخصوصية والحماية</p>
        <h1 class="text-3xl sm:text-4xl font-bold mb-3" style="color:#111827">سياسة الخصوصية</h1>
        <p class="text-sm" style="color:#9CA3AF">آخر تحديث: {{ now()->translatedFormat('F Y') }}</p>
    </div>

    <div class="space-y-8 leading-relaxed" style="color:#374151">

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">مقدمة</h2>
            <p>
                تحرص جمعية كفاءات على حماية خصوصية مستخدمي منصتها. توضّح هذه السياسة أنواع البيانات
                التي نجمعها وكيفية استخدامها وحمايتها عند استخدامك لموقع وخدمات الجمعية.
            </p>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">البيانات التي نجمعها</h2>
            <ul class="list-disc list-inside space-y-2">
                <li>بيانات الحساب: الاسم، البريد الإلكتروني، رقم الجوال.</li>
                <li>بيانات الملف الشخصي التي تضيفها طوعاً.</li>
                <li>سجلات التسجيل في البرامج والمسارات والفرص التطوعية.</li>
                <li>بيانات تقنية مثل عنوان IP ونوع المتصفح لأغراض الأمان والتحسين.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">كيف نستخدم بياناتك</h2>
            <ul class="list-disc list-inside space-y-2">
                <li>تقديم خدمات الجمعية وإدارة حسابك وتسجيلاتك.</li>
                <li>التواصل معك بشأن البرامج والإشعارات المهمة.</li>
                <li>تحسين تجربة الاستخدام وتطوير الخدمات.</li>
                <li>الامتثال للمتطلبات النظامية والقانونية.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">حماية البيانات</h2>
            <p>
                نطبّق إجراءات تقنية وتنظيمية مناسبة لحماية بياناتك من الوصول أو الإفصاح غير المصرّح به،
                بما في ذلك تشفير كلمات المرور والتحقق من البريد الإلكتروني.
            </p>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">حقوقك</h2>
            <p>
                يحق لك الوصول إلى بياناتك وتعديلها أو طلب حذف حسابك. للتواصل بشأن أي طلب يتعلق بخصوصيتك،
                يمكنك مراسلتنا عبر قنوات التواصل الموضّحة في أسفل الصفحة.
            </p>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">التعديلات على السياسة</h2>
            <p>
                قد نحدّث هذه السياسة من وقت لآخر، وسيُعلن عن أي تغييرات جوهرية عبر الموقع. استمرارك في
                استخدام الموقع بعد التحديث يعني موافقتك على السياسة المعدّلة.
            </p>
        </section>

    </div>
</div>

@endsection
