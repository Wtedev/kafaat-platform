<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background-color:#f2fcfd;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2fcfd;">
<tr><td align="center" style="padding:28px 12px;">
<table role="presentation" width="520" cellpadding="0" cellspacing="0" style="width:520px;max-width:100%;background:#ffffff;border-radius:18px;overflow:hidden;">
<tr><td style="background:#1f8a91;height:8px;font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td align="center" style="padding:28px 32px 6px;">
<img src="{{ $logoUrl }}" alt="كفاءات" width="60" height="60" style="display:block;border:0;width:60px;height:60px;">
</td></tr>
<tr><td dir="rtl" align="center" style="padding:10px 32px 30px;direction:rtl;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Tahoma,Arial,sans-serif;color:#2f3d42;font-size:16px;line-height:1.8;">
<p style="margin:0 0 6px;font-size:13px;color:#1f8a91;font-weight:bold;">مشروع بيوت الخبرة</p>
<h1 style="margin:0 0 12px;font-size:22px;color:#0d4c50;">{{ $headline }}</h1>
<p style="margin:0 0 18px;">أهلاً {{ $name }}،<br>{{ $bodyText }}</p>
<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;">
<tr><td align="center" style="border-radius:12px;background:#e8f6f6;padding:10px 22px;color:#0d4c50;font-size:15px;">
حالة الطلب: <b>{{ $statusLabel }}</b>
</td></tr></table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="rtl" style="margin:0 0 20px;background:#f2fcfd;border-radius:12px;text-align:right;">
<tr><td style="padding:7px 12px;color:#6b7c82;font-size:14px;white-space:nowrap;border-bottom:1px solid #e3f1f2;">الجمعية</td><td style="padding:7px 12px;color:#0d4c50;font-size:15px;font-weight:bold;border-bottom:1px solid #e3f1f2;">{{ $r->organization_name }}</td></tr>
<tr><td style="padding:7px 12px;color:#6b7c82;font-size:14px;white-space:nowrap;border-bottom:1px solid #e3f1f2;">رقم الطلب</td><td style="padding:7px 12px;color:#0d4c50;font-size:15px;font-weight:bold;border-bottom:1px solid #e3f1f2;"><span dir="ltr">{{ $r->reference_no }}</span></td></tr>
</table>
<p style="margin:0;font-size:13px;color:#6b7c82;">لأي استفسار ردّوا على هذا البريد أو تواصلوا مع جمعية كفاءات الأهلية.</p>
</td></tr>
</table>
<p style="margin:14px 0 0;font-family:Tahoma,Arial,sans-serif;font-size:12px;color:#8aa3a6;">جمعية كفاءات الأهلية لبناء قدرات الشباب</p>
</td></tr></table>
</body></html>
