<?php

namespace Database\Seeders;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Models\PrivacyPolicyVersion;
use App\Services\Privacy\PrivacyPolicyContentHasher;
use App\Services\Privacy\PrivacyPolicyHtmlSanitizer;
use App\Services\Privacy\PrivacyPolicyService;
use Illuminate\Database\Seeder;

class PrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        if (PrivacyPolicyVersion::query()->where('version', '1.0')->exists()) {
            return;
        }

        $content = PrivacyPolicyHtmlSanitizer::sanitize($this->initialContent());
        $publishedAt = '2026-06-28 00:00:00';

        PrivacyPolicyVersion::query()->create([
            'version' => '1.0',
            'title' => 'سياسة الخصوصية',
            'content' => $content,
            'content_hash' => PrivacyPolicyContentHasher::hash($content),
            'effective_at' => $publishedAt,
            'published_at' => $publishedAt,
            'status' => PrivacyPolicyVersionStatus::Active,
            'requires_reacknowledgement' => false,
        ]);

        PrivacyPolicyService::forgetCache();
    }

    private function initialContent(): string
    {
        return <<<'HTML'
<section>
<h2>مقدمة</h2>
<p>تحرص جمعية كفاءات على حماية خصوصية مستخدمي منصتها. توضّح هذه السياسة أنواع البيانات التي نجمعها وكيفية استخدامها وحمايتها عند استخدامك لموقع وخدمات الجمعية.</p>
</section>
<section>
<h2>البيانات التي نجمعها</h2>
<ul>
<li>بيانات الحساب: الاسم الرباعي، البريد الإلكتروني، رقم الجوال، نوع الهوية ورقم الهوية أو الإقامة، وتاريخ الميلاد.</li>
<li>بيانات الملف الشخصي التي تضيفها طوعاً.</li>
<li>سجلات التسجيل في البرامج والمسارات والفرص التطوعية.</li>
<li>بيانات تقنية مثل عنوان IP ونوع المتصفح لأغراض الأمان والتحسين.</li>
</ul>
</section>
<section>
<h2>كيف نستخدم بياناتك</h2>
<ul>
<li>تقديم خدمات الجمعية وإدارة حسابك وتسجيلاتك.</li>
<li>التواصل معك بشأن البرامج والإشعارات المهمة.</li>
<li>تحسين تجربة الاستخدام وتطوير الخدمات.</li>
<li>الامتثال للمتطلبات النظامية والقانونية.</li>
</ul>
</section>
<section>
<h2>حماية البيانات</h2>
<p>نطبّق إجراءات تقنية وتنظيمية مناسبة لحماية بياناتك من الوصول أو الإفصاح غير المصرّح به، بما في ذلك تشفير كلمات المرور والتحقق من البريد الإلكتروني وحماية أرقام الهوية.</p>
</section>
<section>
<h2>حقوقك</h2>
<p>يحق لك الوصول إلى بياناتك وتعديلها أو طلب حذف حسابك. للتواصل بشأن أي طلب يتعلق بخصوصيتك، يمكنك مراسلتنا عبر قنوات التواصل الموضّحة في أسفل الصفحة.</p>
</section>
<section>
<h2>التعديلات على السياسة</h2>
<p>قد نحدّث هذه السياسة من وقت لآخر. عند نشر تعديل جوهري قد نطلب منك الاطلاع على النسخة المحدّثة وتأكيد الإقرار بها قبل الاستمرار في استخدام بعض الخدمات.</p>
</section>
HTML;
    }
}
