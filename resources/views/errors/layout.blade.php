{{--
  Self-contained error shell (no Vite/Tailwind).
  Survives missing build assets during deploys and keeps Filament/admin on Laravel’s
  normal HTTP exception views without a custom admin error stack.
--}}
@php
    $code = $code ?? '—';
    $title = $title ?? 'حدث خطأ';
    $message = $message ?? 'يرجى المحاولة لاحقاً.';
    $hint = $hint ?? null;
    $requestId = $requestId ?? null;
    $autoRefreshSeconds = isset($autoRefreshSeconds) ? (int) $autoRefreshSeconds : 0;
    $showReload = $showReload ?? ($autoRefreshSeconds > 0);
    $showHome = $showHome ?? true;
    $logoPath = config('brand.logos.kafaat', 'images/brand/kafaat-logo.svg');
    $brand = config('brand.primary', '#335483');
    $brandDark = config('brand.primary_dark', '#243a55');
    $brandLight = config('brand.primary_light', '#e9eff6');
    $brandBorder = config('brand.primary_border', '#c5d4e4');
@endphp
<!DOCTYPE html>
<html lang="ar-SA-u-nu-latn" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>{{ $title }} — كفاءات</title>
    @if($autoRefreshSeconds > 0)
        <meta http-equiv="refresh" content="{{ $autoRefreshSeconds }}">
    @endif
    <style>
        @font-face {
            font-family: 'FF Shamel';
            src: url('{{ asset('fonts/shamel/FFShamelFamily-SansOneBook.ttf') }}') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'FF Shamel';
            src: url('{{ asset('fonts/shamel/FFShamelFamily-SansOneBold.ttf') }}') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --brand: {{ $brand }};
            --brand-dark: {{ $brandDark }};
            --brand-light: {{ $brandLight }};
            --brand-border: {{ $brandBorder }};
            --ink: #111827;
            --muted: #6B7280;
            --card: #ffffff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
            font-family: 'FF Shamel', 'Segoe UI', Tahoma, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(ellipse 80% 50% at 100% 0%, rgba(51, 84, 131, 0.12), transparent 55%),
                radial-gradient(ellipse 60% 40% at 0% 100%, rgba(26, 147, 153, 0.08), transparent 50%),
                linear-gradient(150deg, #EEF5FB 0%, #F3F7FB 55%, #e9eff6 100%);
            -webkit-font-smoothing: antialiased;
        }

        .shell {
            width: 100%;
            max-width: 28rem;
            text-align: center;
        }

        .brand {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.75rem;
            text-decoration: none;
            color: inherit;
        }

        .brand img {
            height: 3.25rem;
            width: auto;
        }

        .brand-sub {
            margin: 0.35rem 0 0;
            font-size: 0.875rem;
            color: var(--muted);
        }

        .card {
            background: var(--card);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 1.5rem;
            box-shadow: 0 18px 40px rgba(36, 58, 85, 0.10);
            padding: 2rem 1.75rem 1.75rem;
        }

        .code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 4.5rem;
            padding: 0.35rem 0.85rem;
            margin-bottom: 1rem;
            border-radius: 999px;
            border: 1px solid var(--brand-border);
            background: var(--brand-light);
            color: var(--brand);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        h1 {
            margin: 0 0 0.65rem;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.45;
            color: var(--brand-dark);
        }

        .message {
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.75;
            color: #374151;
        }

        .hint {
            margin: 1rem 0 0;
            padding: 0.85rem 1rem;
            border-radius: 1rem;
            border: 1px solid var(--brand-border);
            background: var(--brand-light);
            font-size: 0.875rem;
            line-height: 1.7;
            color: var(--brand-dark);
        }

        .request-id {
            margin: 1rem 0 0;
            font-size: 0.75rem;
            color: #9CA3AF;
            word-break: break-all;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            min-height: 2.75rem;
            padding: 0.7rem 1.35rem;
            border-radius: 0.9rem;
            border: none;
            font: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .btn:focus-visible {
            outline: 2px solid var(--brand);
            outline-offset: 3px;
        }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--brand) 0%, #406688 100%);
            box-shadow: 0 8px 18px rgba(51, 84, 131, 0.22);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(51, 84, 131, 0.28);
        }

        .btn-secondary {
            color: var(--brand);
            background: #fff;
            border: 1.5px solid var(--brand-border);
        }

        .btn-secondary:hover {
            background: var(--brand-light);
        }

        .countdown {
            margin: 1.15rem 0 0;
            font-size: 0.8rem;
            color: var(--muted);
        }

        .countdown strong {
            color: var(--brand);
            font-variant-numeric: tabular-nums;
        }

        .footer {
            margin-top: 1.35rem;
            font-size: 0.8rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <main class="shell" role="main">
        <a class="brand" href="{{ url('/') }}" aria-label="كفاءات — الرئيسية">
            <img src="{{ asset($logoPath) }}" alt="كفاءات" width="185" height="56" />
            <p class="brand-sub">جمعية كفاءات لبناء قدرات الشباب</p>
        </a>

        <div class="card">
            <div class="code" aria-hidden="true">{{ $code }}</div>
            <h1>{{ $title }}</h1>
            <p class="message">{{ $message }}</p>

            @if($hint)
                <p class="hint">{{ $hint }}</p>
            @endif

            @if($requestId)
                <p class="request-id">مرجع الطلب: {{ $requestId }}</p>
            @endif

            @if($showReload || $showHome)
                <div class="actions">
                    @if($showReload)
                        <button type="button" class="btn btn-primary" onclick="window.location.reload()">
                            إعادة تحميل
                        </button>
                    @endif
                    @if($showHome)
                        <a class="btn btn-secondary" href="{{ url('/') }}">العودة للرئيسية</a>
                    @endif
                </div>
            @endif

            @if($autoRefreshSeconds > 0)
                <p class="countdown" id="error-countdown" data-seconds="{{ $autoRefreshSeconds }}">
                    ستُحدَّث الصفحة تلقائياً خلال <strong id="error-countdown-value">{{ $autoRefreshSeconds }}</strong> ثانية
                </p>
            @endif
        </div>

        <p class="footer">منصة كفاءات</p>
    </main>

    @if($autoRefreshSeconds > 0)
        <script>
            (function () {
                var el = document.getElementById('error-countdown-value');
                var wrap = document.getElementById('error-countdown');
                if (!el || !wrap) return;
                var remaining = parseInt(wrap.getAttribute('data-seconds'), 10) || 0;
                var timer = setInterval(function () {
                    remaining -= 1;
                    if (remaining <= 0) {
                        clearInterval(timer);
                        el.textContent = '0';
                        window.location.reload();
                        return;
                    }
                    el.textContent = String(remaining);
                }, 1000);
            })();
        </script>
    @endif
</body>
</html>
