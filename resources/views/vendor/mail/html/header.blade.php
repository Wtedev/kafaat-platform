@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset(config('brand.logos.kafaat_mail', config('brand.logos.kafaat_mark', 'images/brand/kafaat-logo-mail.png'))) }}" class="logo" alt="{{ config('app.name', 'منصة كفاءات') }}">
</a>
</td>
</tr>
