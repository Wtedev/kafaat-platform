@php
    $logoUrl = $logoUrl ?? (rtrim((string) config('site.website_url'), '/').'/'.ltrim((string) config('brand.logos.kafaat_mail'), '/'));
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
<a href="{{ rtrim((string) config('site.website_url'), '/') }}" style="text-decoration:none;">
<img src="{{ $logoUrl }}" alt="كفاءات" width="64" height="64" style="display:block;margin:0 auto;border:0;width:64px;height:64px;">
</a>
</td>
</tr>
<tr>
<td dir="rtl" align="center" style="padding:8px 32px 32px;direction:rtl;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.7;color:#52525b;">
{!! $bodyHtml !!}
@if (filled($groupUrl))
<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:8px auto 20px;">
<tr>
<td align="center" bgcolor="#335483" style="border-radius:4px;background-color:#335483;">
<a href="{{ $groupUrl }}" target="_blank" rel="noopener" style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-weight:bold;">{{ \App\Support\ProgramApprovalMail::BUTTON_LABEL }}</a>
</td>
</tr>
</table>
@elseif (! empty($showPendingLine))
<p style="margin:0 0 16px;text-align:center;">{{ \App\Support\ProgramApprovalMail::PENDING_LINE }}</p>
@endif
</td>
</tr>
</table>
<p style="margin:16px 0 0;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#a1a1aa;">© {{ date('Y') }} {{ config('app.name') }}. جميع الحقوق محفوظة.</p>
</td>
</tr>
</table>
</body>
</html>
