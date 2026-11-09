@php
    $siteBase = rtrim((string) config('site.website_url'), '/');
    $logoUrl = $logoUrl ?? ($siteBase.'/'.ltrim((string) config('brand.logos.kafaat_mail'), '/'));
    $subjectLine = $subjectLine ?? \App\Support\TatawaaWaeiInviteMail::SUBJECT;
    $registerUrl = $registerUrl ?? \App\Support\TatawaaWaeiInviteMail::registerUrl();
    // Prefer an explicit src (cid:… or https://…). Never use data: URIs — Gmail strips them.
    $heroSrc = $heroSrc
        ?? $heroUrl
        ?? \App\Support\TatawaaWaeiInviteMail::heroPublicUrl();
    $openingLine = $openingLine
        ?? \App\Support\TatawaaWaeiInviteMail::openingLine($recipientName ?? null);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background-color:#fafafa;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa;">
<tr>
<td align="center" style="padding:24px 12px;">
<table role="presentation" width="570" cellpadding="0" cellspacing="0" style="width:570px;max-width:100%;background-color:#ffffff;border-radius:4px;">
<tr>
<td align="center" style="padding:28px 32px 8px;text-align:center;">
<a href="{{ $siteBase }}" style="text-decoration:none;">
<img src="{{ $logoUrl }}" alt="كفاءات" width="64" height="64" style="display:block;margin:0 auto;border:0;width:64px;height:64px;">
</a>
</td>
</tr>
<tr>
<td dir="rtl" align="center" style="padding:16px 32px 32px;direction:rtl;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.7;color:#52525b;">
<p style="margin:0 0 16px;text-align:center;">{{ $openingLine }}</p>
<p style="margin:0 0 16px;text-align:center;">نعرف أنك تبحث عن فرصتك المهنية القادمة، ويسعدنا أن نشاركك فرصة تضيف لك خبرة حقيقية تعزّز مسيرتك.</p>
<p style="margin:0 0 16px;text-align:center;">ندعوك للانضمام إلى مبادرة «تطوع واعي» من جمعية كفاءات الأهلية، وهي مبادرة تمكين وتوعية رقمية تحوّل المتطوعين من مشاركين مؤقتين إلى صنّاع محتوى محترفين.</p>
<p style="margin:0 0 8px;text-align:center;">• ورش تدريبية في التمكين الرقمي وصناعة المحتوى</p>
<p style="margin:0 0 8px;text-align:center;">• تطوير مهاراتك القيادية</p>
<p style="margin:0 0 16px;text-align:center;">• فرصة الانتقاء لتمثيل حملة «تطوع واعي»</p>
@if (filled($heroSrc))
<img src="{{ $heroSrc }}" alt="تطوع واعي" width="506" style="display:block;margin:0 auto 16px;border:0;width:100%;max-width:506px;height:auto;border-radius:4px;">
@endif
<p style="margin:0 0 16px;text-align:center;">المقاعد محدودة، سجّل الآن:</p>
<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:8px auto 20px;">
<tr>
<td align="center" bgcolor="#335483" style="border-radius:4px;background-color:#335483;">
<a href="{{ $registerUrl }}" target="_blank" rel="noopener" style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-weight:bold;">{{ \App\Support\TatawaaWaeiInviteMail::BUTTON_LABEL }}</a>
</td>
</tr>
</table>
<p style="margin:0 0 16px;text-align:center;">كن جزءاً من جيلٍ يتطوع بوعي.. ويصنع أثراً يُستدام.</p>
<p style="margin:0 0 4px;text-align:center;">مع التحية،</p>
<p style="margin:0;text-align:center;">فريق جمعية كفاءات الأهلية</p>
</td>
</tr>
</table>
<p style="margin:16px 0 0;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#a1a1aa;">© {{ date('Y') }} {{ config('app.name') }}. جميع الحقوق محفوظة.</p>
</td>
</tr>
</table>
</body>
</html>
