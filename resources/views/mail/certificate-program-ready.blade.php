@php
    $siteBase = rtrim((string) config('site.website_url'), '/');
    $logoUrl = $logoUrl ?? ($siteBase.'/'.ltrim((string) config('brand.logos.kafaat_mail'), '/'));
    $social = collect(config('site.social', []))->filter(fn ($item): bool => filled($item['url'] ?? null));
    $phoneDigits = preg_replace('/\D+/', '', (string) config('site.contact_phone_e164', ''));
    $whatsappHref = $phoneDigits !== '' ? 'https://wa.me/'.$phoneDigits : null;
    $socialIconUrl = fn (string $key): string => $siteBase.'/images/mail/social/'.$key.'.png?v=20261103b';
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
<p style="margin:0 0 16px;text-align:center;">مرحبًا {{ $beneficiaryName }}</p>
<p style="margin:0 0 16px;text-align:center;">سعدنا بمشاركتك معنا في {{ $programTitle }}، ونقدّر حضورك وتفاعلك الذي كان جزءًا من نجاح هذه التجربة.</p>
<p style="margin:0 0 16px;text-align:center;">يسرّنا إبلاغك بصدور شهادتك. شهادتك مرفقة بهذا البريد بصيغة PDF، ويمكنك فتحها من المرفقات، أو الاطلاع عليها وتحميلها من خلال الزر أدناه.</p>
<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:8px auto 20px;">
<tr>
<td align="center" bgcolor="#335483" style="border-radius:4px;background-color:#335483;">
<a href="{{ $buttonUrl }}" target="_blank" rel="noopener" style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-weight:bold;">{{ $buttonLabel }}</a>
</td>
</tr>
</table>
<p style="margin:0 0 16px;text-align:center;">نتطلع إلى لقائك في برامج وتجارب قادمة، تحمل لك مزيدًا من المعرفة والأثر.</p>
<p style="margin:0 0 4px;text-align:center;">مع خالص التقدير،</p>
<p style="margin:0 0 28px;text-align:center;">فريق جمعية كفاءات الأهلية</p>
<p style="margin:0 0 16px;text-align:center;font-size:14px;line-height:1.8;color:#71717a;">ولأن رحلتنا معكم لا تنتهي بانتهاء البرنامج، يسعدنا بقاؤكم بالقرب منّا عبر منصات التواصل الاجتماعي، للاطلاع على برامجنا وفرصنا القادمة.</p>
@if ($social->isNotEmpty() || filled($whatsappHref))
{{-- dir=ltr keeps icon order stable across clients; matches the public footer sequence. --}}
<table role="presentation" align="center" dir="ltr" cellpadding="0" cellspacing="0" style="margin:0 auto;border-collapse:collapse;">
<tr>
@foreach ($social as $item)
@php $iconKey = (string) ($item['key'] ?? ''); @endphp
@if ($iconKey !== '' && in_array($iconKey, ['youtube', 'linkedin', 'x', 'tiktok', 'instagram'], true))
<td align="center" valign="middle" style="padding:0 6px;">
<a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $item['label'] }}" style="display:inline-block;text-decoration:none;border:0;line-height:0;">
<img src="{{ $socialIconUrl($iconKey) }}" alt="{{ $item['label'] }}" width="44" height="44" style="display:block;border:0;outline:none;width:44px;height:44px;max-width:44px;">
</a>
</td>
@endif
@endforeach
@if (filled($whatsappHref))
<td align="center" valign="middle" style="padding:0 6px;">
<a href="{{ $whatsappHref }}" target="_blank" rel="noopener noreferrer" aria-label="واتساب" style="display:inline-block;text-decoration:none;border:0;line-height:0;">
<img src="{{ $socialIconUrl('whatsapp') }}" alt="واتساب" width="44" height="44" style="display:block;border:0;outline:none;width:44px;height:44px;max-width:44px;">
</a>
</td>
@endif
</tr>
</table>
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
