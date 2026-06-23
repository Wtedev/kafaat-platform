@extends('layouts.public')

@section('title', 'الشروط والأحكام — كفاءات')
@section('meta_description', 'الشروط والأحكام التي تحكم استخدام موقع وخدمات جمعية كفاءات.')

@section('content')

<div class="max-w-3xl mx-auto text-right">

    <div class="mb-10">
        <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color:#1a9399">الاستخدام والالتزامات</p>
        <h1 class="text-3xl sm:text-4xl font-bold mb-3" style="color:#111827">الشروط والأحكام</h1>
        <p class="text-sm" style="color:#9CA3AF">آخر تحديث: {{ now()->translatedFormat('F Y') }}</p>
    </div>

    <div class="space-y-8 leading-relaxed" style="color:#374151">

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">قبول الشروط</h2>
            <p>
                باستخدامك موقع وخدمات جمعية كفاءات فإنك توافق على الالتزام بهذه الشروط والأحكام. إذا كنت لا توافق على أي
                منها، يُرجى عدم استخدام الموقع.
            </p>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">استخدام الحساب</h2>
            <ul class="list-disc list-inside space-y-2">
                <li>أنت مسؤول عن الحفاظ على سرية بيانات الدخول الخاصة بك.</li>
                <li>تلتزم بتقديم معلومات صحيحة ومحدّثة عند التسجيل.</li>
                <li>يُحظر استخدام الموقع لأي غرض غير مشروع أو مخالف للأنظمة.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">التسجيل في البرامج والفرص</h2>
            <p>
                يخضع التسجيل في البرامج التدريبية والفرص التطوعية للمراجعة والموافقة، وقد يُلغى التسجيل في حال
                مخالفة الشروط أو عدم استيفاء المتطلبات.
            </p>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">الملكية الفكرية</h2>
            <p>
                جميع المحتويات والمواد المنشورة على موقع الجمعية مملوكة لجمعية كفاءات أو مرخّصة لها، ولا يجوز إعادة
                استخدامها دون إذن مسبق.
            </p>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">إخلاء المسؤولية</h2>
            <p>
                تُقدَّم خدمات الجمعية "كما هي"، وتسعى الجمعية لضمان دقة المحتوى واستمرارية الخدمة دون أن تتحمل
                مسؤولية أي انقطاع أو خطأ خارج عن إرادتها.
            </p>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3" style="color:#111827">تعديل الشروط</h2>
            <p>
                تحتفظ الجمعية بحق تعديل هذه الشروط في أي وقت، ويسري التعديل فور نشره على الموقع.
            </p>
        </section>

    </div>
</div>

@endsection
