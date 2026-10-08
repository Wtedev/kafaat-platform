@php
    $logoUrl = $logoUrl ?? \App\Support\DataForumAcceptance::logoUrl();
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ \App\Support\DataForumAcceptance::SUBJECT }}</title>
</head>
<body style="margin:0;padding:0;background-color:#fafafa;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa;">
<tr>
<td align="center" style="padding:24px 12px;">
<table role="presentation" width="570" cellpadding="0" cellspacing="0" style="width:570px;max-width:100%;background-color:#ffffff;border-radius:4px;">
<tr>
<td align="center" style="padding:28px 32px 8px;text-align:center;">
<a href="{{ rtrim((string) config('site.website_url'), '/') }}" style="text-decoration:none;">
<img src="{{ $logoUrl }}" alt="كفاءات" width="64" height="64" style="display:block;margin:0 auto;border:0;width:64px;height:64px;">
</a>
</td>
</tr>
<tr>
<td dir="rtl" align="center" style="padding:8px 32px 32px;direction:rtl;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.7;color:#52525b;">
<p style="margin:0 0 16px;text-align:center;">رسالة من فريق كفاءات بخصوص ملتقى تحليل البيانات في القطاع غير الربحي – النسخة الثانية.</p>
<p style="margin:0 0 16px;text-align:center;">يسر جمعية كفاءات الأهلية لبناء قدرات الشباب أن تتقدم لك بخالص التهنئة بمناسبة <strong>قبولك النهائي في الملتقى</strong>.</p>
<p style="margin:0 0 8px;text-align:center;"><strong>تفاصيل الملتقى:</strong></p>
<p style="margin:0 0 8px;text-align:center;"><strong>تاريخ الانطلاق:</strong> 10 أكتوبر 2026م</p>
<p style="margin:0 0 8px;text-align:center;"><strong>نوع اللقاء:</strong> عن بُعد عبر منصة Zoom</p>
<p style="margin:0 0 16px;text-align:center;"><strong>مواعيد اللقاء:</strong> تُقام المرحلة النظرية من 10 إلى 12 أكتوبر، من الساعة 4 مساءً حتى الساعة 7 مساءً. أما مواعيد المراحل اللاحقة فسيتم تزويد المشاركين بها تباعًا وفق سير الملتقى.</p>
<p style="margin:0 0 8px;text-align:center;"><strong>تنويه:</strong></p>
<p style="margin:0 0 16px;text-align:center;">نأمل منك الانضمام إلى مجموعة الملتقى على تيليجرام لمتابعة التنبيهات والتحديثات وروابط اللقاءات والمعلومات المتعلقة بمراحل الملتقى.</p>
@if (filled($telegramUrl))
<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:8px auto 20px;">
<tr>
<td align="center" bgcolor="#335483" style="border-radius:4px;background-color:#335483;">
<a href="{{ $telegramUrl }}" target="_blank" rel="noopener" style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-weight:bold;">الانضمام إلى مجموعة تيليجرام</a>
</td>
</tr>
</table>
@else
<p style="margin:0 0 16px;text-align:center;">{{ \App\Support\DataForumAcceptance::TELEGRAM_PENDING_LINE }}</p>
@endif
<p style="margin:0 0 16px;text-align:center;">سعداء بانضمامك، ونتطلع إلى مشاركتك في <strong>رحلة</strong> <strong>تبدأ بالبيانات.. وتمتد إلى ما وراء الأرقام.</strong></p>
<p style="margin:0;text-align:center;">مع تحيات فريق جمعية كفاءات الأهلية لبناء قدرات الشباب</p>
</td>
</tr>
</table>
<p style="margin:16px 0 0;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#a1a1aa;">© {{ date('Y') }} {{ config('app.name') }}. جميع الحقوق محفوظة.</p>
</td>
</tr>
</table>
</body>
</html>
