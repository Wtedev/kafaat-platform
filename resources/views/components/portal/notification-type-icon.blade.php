@props([
    'type' => null,
    'compact' => false,
])

@php
    use App\Enums\InboxNotificationType;
    use App\Support\InboxNotificationIcon;
    use BladeUI\Icons\Exceptions\SvgNotFound;

    if (is_string($type)) {
        $type = InboxNotificationType::tryFrom($type);
    }

    $iconName = InboxNotificationIcon::heroiconFor($type);
    $iconClass = $compact ? 'h-4 w-4' : 'h-5 w-5';

    try {
        $iconHtml = svg($iconName, $iconClass)->toHtml();
    } catch (SvgNotFound) {
        $iconHtml = svg(InboxNotificationIcon::FALLBACK, $iconClass)->toHtml();
    }
@endphp

{!! $iconHtml !!}
